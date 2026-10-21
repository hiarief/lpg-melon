<?php

namespace App\Services;

use App\Models\AccountTransfer;
use App\Models\Courier;
use App\Models\CourierDeposit;
use App\Models\DailyExpense;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\ExternalDebt;
use App\Models\OutletContractPayment;
use App\Models\Period;

/**
 * Membangun seluruh angka ringkasan satu periode dalam satu tempat,
 * supaya web (Blade) dan API (Flutter) selalu memakai rumus yang sama.
 */
class PeriodSummaryService
{
    const HPP_PER_TABUNG = 16_000;

    public function build(Period $period): array
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);

        // ── DO & STOK ───────────────────────────────────────────────────────
        // DO murni bulan ini; carry-over TIDAK dihitung sebagai stok masuk
        // (nilainya sudah tercermin di opening_stock bulan ini).
        $doQuery = fn () => DeliveryOrder::where('period_id', $period->id)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'));

        $totalDoQty      = (int) $doQuery()->sum('qty');
        $doValueByOutlet = $doQuery()->selectRaw('outlet_id, SUM(qty) as qty, SUM(qty * price_per_unit) as value')
            ->groupBy('outlet_id')->get()->keyBy('outlet_id')
            ->map(fn ($r) => ['qty' => (int) $r->qty, 'value' => (int) $r->value]);
        $carryoverByOutlet = DeliveryOrder::where('period_id', $period->id)
            ->where('notes', 'like', '%Carry-over%')
            ->selectRaw('outlet_id, SUM(qty) as qty')->groupBy('outlet_id')
            ->pluck('qty', 'outlet_id');
        $totalCarryoverQty = (int) $carryoverByOutlet->sum();

        $totalDistQty = (int) Distribution::where('period_id', $period->id)->sum('qty');
        $stockRemaining = (int) ($period->opening_stock + $totalDoQty - $totalDistQty);

        // ── PENJUALAN ───────────────────────────────────────────────────────
        $salesByCustomer = Distribution::with('customer')->where('period_id', $period->id)
            ->selectRaw('customer_id, SUM(qty) as qty, SUM(qty * price_per_unit) as value, SUM(paid_amount) as paid')
            ->groupBy('customer_id')->get()->map(function ($r) {
                return [
                    'customer_id'   => $r->customer_id,
                    'customer_name' => $r->customer?->name ?? '-',
                    'customer_type' => $r->customer?->type ?? 'regular',
                    'qty'           => (int) $r->qty,
                    'value'         => (int) $r->value,
                    'paid'          => (int) $r->paid,
                    'piutang'       => (int) ($r->value - $r->paid),
                    'avg_price'     => $r->qty > 0 ? (int) round($r->value / $r->qty) : 0,
                ];
            })->values()->all();

        $totalSales  = array_sum(array_column($salesByCustomer, 'value'));
        $totalPaid   = array_sum(array_column($salesByCustomer, 'paid'));
        $totalModal  = $totalDistQty * self::HPP_PER_TABUNG;
        $grossMargin = $totalSales - $totalModal;

        // ── PENGELUARAN ─────────────────────────────────────────────────────
        $expensesByCategory = DailyExpense::where('period_id', $period->id)
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')
            ->pluck('total', 'category')->map(fn ($v) => (int) $v);
        $totalExpense = (int) $expensesByCategory->sum();

        // ── SETORAN KURIR & TRANSFER ────────────────────────────────────────
        $totalDeposited  = (int) CourierDeposit::where('period_id', $period->id)->sum('net_amount');
        $totalAdminFees  = (int) CourierDeposit::where('period_id', $period->id)->sum('admin_fee');
        $totalTransferred = (int) AccountTransfer::where('period_id', $period->id)->sum('amount');
        $totalSurplus     = (int) AccountTransfer::where('period_id', $period->id)->sum('surplus');

        $penampungBalance = (int) ($period->opening_penampung + $totalDeposited - $totalTransferred - $totalAdminFees);

        // ── SURPLUS PER TRANSFER (tabungan di rek utama) ────────────────────
        $surplusTransfers = AccountTransfer::where('period_id', $period->id)->where('surplus', '>', 0)
            ->orderBy('transfer_date')->get()
            ->map(fn ($t) => [
                'transfer_date' => $t->transfer_date?->toDateString(),
                'amount'        => (int) $t->amount,
                'surplus'       => (int) $t->surplus,
                'notes'         => $t->notes,
            ])->all();

        // ── PIUTANG DO ──────────────────────────────────────────────────────
        // Urutan pelunasan: periode sebelumnya (belum diwakili carry-over)
        // → carry-over bulan ini → DO bulan ini.
        $carryoverUnpaid = DeliveryOrder::with('outlet')->where('period_id', $period->id)
            ->where('notes', 'like', '%Carry-over%')
            ->whereIn('payment_status', ['unpaid', 'partial'])->get();
        $currentUnpaid = DeliveryOrder::with('outlet')->where('period_id', $period->id)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('do_date')->get();

        [$prevMonth, $prevYear] = $period->month === 1
            ? [12, $period->year - 1]
            : [$period->month - 1, $period->year];
        $prevPeriod = Period::where('year', $prevYear)->where('month', $prevMonth)->first();

        $carriedOutlets = $carryoverUnpaid->pluck('outlet_id')->unique();
        $prevUnpaid = $prevPeriod
            ? DeliveryOrder::with(['outlet', 'period'])->where('period_id', $prevPeriod->id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotIn('outlet_id', $carriedOutlets)
                ->orderBy('do_date')->get()
            : collect();

        $allUnpaid = $prevUnpaid->concat($carryoverUnpaid)->concat($currentUnpaid);

        $piutangDO = [
            'prev_period' => $prevUnpaid->map(fn ($d) => [
                'id' => $d->id, 'outlet_name' => $d->outlet?->name,
                'do_date' => $d->do_date?->toDateString(),
                'period_label' => $d->period?->label,
                'qty' => (int) $d->qty, 'value' => (int) $d->totalValue(),
                'paid' => (int) $d->paid_amount, 'remaining' => (int) $d->remainingAmount(),
                'status' => $d->payment_status, 'is_carryover' => false,
            ])->all(),
            'carryover' => $carryoverUnpaid->map(fn ($d) => [
                'id' => $d->id, 'outlet_name' => $d->outlet?->name,
                'do_date' => $d->do_date?->toDateString(),
                'qty' => (int) $d->qty, 'value' => (int) $d->totalValue(),
                'paid' => (int) $d->paid_amount, 'remaining' => (int) $d->remainingAmount(),
                'status' => $d->payment_status, 'is_carryover' => true,
            ])->all(),
            'current' => $currentUnpaid->map(fn ($d) => [
                'id' => $d->id, 'outlet_name' => $d->outlet?->name,
                'do_date' => $d->do_date?->toDateString(),
                'qty' => (int) $d->qty, 'value' => (int) $d->totalValue(),
                'paid' => (int) $d->paid_amount, 'remaining' => (int) $d->remainingAmount(),
                'status' => $d->payment_status, 'is_carryover' => false,
            ])->all(),
            'total_value' => (int) $allUnpaid->sum(fn ($d) => $d->remainingAmount()),
        ];

        // ── KONTRAK PANGKALAN ───────────────────────────────────────────────
        $contractPayments = OutletContractPayment::with('outlet')->where('period_id', $period->id)->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'outlet_id' => $p->outlet_id, 'outlet_name' => $p->outlet?->name,
                'calculated_amount' => (int) $p->calculated_amount,
                'paid_amount' => (int) $p->paid_amount,
                'paid_date' => $p->paid_date?->toDateString(),
                'status' => $p->status,
                'remaining' => (int) max(0, $p->calculated_amount - $p->paid_amount),
            ])->all();

        // ── GAJI KURIR ──────────────────────────────────────────────────────
        $courierWages = Courier::where('is_active', true)->get()->map(function ($c) use ($period) {
            $qty = (int) Distribution::where('period_id', $period->id)->where('courier_id', $c->id)->sum('qty');
            return ['courier_id' => $c->id, 'name' => $c->name, 'qty' => $qty, 'wage' => $qty * (int) $c->wage_per_unit];
        })->all();

        // ── PIUTANG EXTERNAL ────────────────────────────────────────────────
        $externalIn  = (int) ExternalDebt::where('period_id', $period->id)->where('type', 'in')->sum('amount');
        $externalOut = (int) ExternalDebt::where('period_id', $period->id)->where('type', 'out')->sum('amount');
        $externalNet = (int) ($period->opening_external_debt + $externalIn - $externalOut);

        // ── KAS HARIAN (saldo berjalan) ─────────────────────────────────────
        $expenseByDay = DailyExpense::where('period_id', $period->id)
            ->selectRaw('DAY(expense_date) as day, SUM(amount) as total')->groupBy('day')
            ->pluck('total', 'day')->map(fn ($v) => (int) $v);
        $salesByDay = Distribution::where('period_id', $period->id)
            ->selectRaw('DAY(dist_date) as day, SUM(paid_amount) as total')->groupBy('day')
            ->pluck('total', 'day')->map(fn ($v) => (int) $v);
        $depositByDay = CourierDeposit::where('period_id', $period->id)
            ->selectRaw('DAY(deposit_date) as day, SUM(net_amount) as net, SUM(admin_fee) as admin')
            ->groupBy('day')->get()->keyBy('day')
            ->map(fn ($r) => ['net' => (int) $r->net, 'admin' => (int) $r->admin]);

        $cashRunning = (int) $period->opening_cash;
        $bankRunning = (int) $period->opening_penampung;
        $dailyCash = [];
        $dailyBank = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $cashRunning += ($salesByDay[$day] ?? 0)
                - ($expenseByDay[$day] ?? 0)
                - ($depositByDay[$day]['net'] ?? 0)
                - ($depositByDay[$day]['admin'] ?? 0);
            $bankRunning += ($depositByDay[$day]['net'] ?? 0) - ($depositByDay[$day]['admin'] ?? 0);

            $dailyCash[] = ['day' => $day, 'balance' => $cashRunning];
            $dailyBank[] = ['day' => $day, 'balance' => $bankRunning];
        }

        $netCash = $cashRunning;
        $finalBank = $bankRunning;

        // ── ALUR DANA (mapping uang → profit) ───────────────────────────────
        $alurDana = [
            [
                'label'  => 'Penjualan Kotor',
                'value'  => $totalSales,
                'detail' => $totalDistQty . ' tabung × harga jual rata-rata',
            ],
            [
                'label'  => '− Modal (HPP)',
                'value'  => -$totalModal,
                'detail' => $totalDistQty . ' tabung × Rp 16.000',
            ],
            [
                'label'  => '− Pengeluaran Operasional',
                'value'  => -$totalExpense,
                'detail' => number_format(count($expensesByCategory)) . ' kategori',
            ],
            [
                'label'  => '− Biaya Admin Transfer',
                'value'  => -$totalAdminFees,
                'detail' => 'potongan setoran kurir',
            ],
            [
                'label'  => '− Gaji Kurir',
                'value'  => -array_sum(array_column($courierWages, 'wage')),
                'detail' => 'Rp ' . number_format(Courier::where('is_active', true)->avg('wage_per_unit') ?: 0) . '/tabung',
            ],
            [
                'label'  => 'Profit Bersih (sebelum piutang)',
                'value'  => $grossMargin - $totalExpense - $totalAdminFees - array_sum(array_column($courierWages, 'wage')),
                'detail' => 'margin − biaya',
            ],
            [
                'label'  => '± Piutang Customer',
                'value'  => -(int) ($totalSales - $totalPaid),
                'detail' => 'penjualan yang belum cair',
            ],
            [
                'label'  => 'Profit Kas (uang di tangan)',
                'value'  => $netCash - (int) $period->opening_cash,
                'detail' => 'saldo kas akhir − kas awal',
            ],
        ];

        return [
            'period' => [
                'id' => $period->id,
                'label' => $period->label,
                'year' => (int) $period->year,
                'month' => (int) $period->month,
                'status' => $period->status,
                'opening_cash' => (int) $period->opening_cash,
                'opening_penampung' => (int) $period->opening_penampung,
                'opening_stock' => (int) $period->opening_stock,
                'opening_surplus' => (int) ($period->opening_surplus ?? 0),
                'opening_external_debt' => (int) $period->opening_external_debt,
            ],
            'kpi' => [
                'stock_remaining'   => $stockRemaining,
                'total_do_qty'      => $totalDoQty,
                'total_carryover_qty' => $totalCarryoverQty,
                'total_dist_qty'    => $totalDistQty,
                'total_sales'       => $totalSales,
                'total_paid'        => $totalPaid,
                'total_modal'       => $totalModal,
                'gross_margin'      => $grossMargin,
                'total_expense'     => $totalExpense,
                'net_cash'          => $netCash,
                'final_bank'        => $finalBank,
                'total_transferred' => $totalTransferred,
                'total_surplus'     => $totalSurplus,
                'total_deposited'   => $totalDeposited,
                'penampung_balance' => $penampungBalance,
                'external_debt_net' => $externalNet,
                'piutang_do_total'  => $piutangDO['total_value'],
                'courier_wage_total' => array_sum(array_column($courierWages, 'wage')),
                'profit_bersih'     => $grossMargin - $totalExpense - $totalAdminFees - array_sum(array_column($courierWages, 'wage')),
                'profit_kas'        => $netCash - (int) $period->opening_cash,
                'savings_balance'   => (int) $period->totalSavingsBalance(),
                'rekomendasi_tarikan' => max(0, (int) round(($netCash - (int) $period->opening_cash) * 0.70)),
            ],
            'sales_by_customer'   => $salesByCustomer,
            'expenses_by_category' => $expensesByCategory,
            'courier_wages'       => $courierWages,
            'contract_payments'   => $contractPayments,
            'surplus_transfers'   => $surplusTransfers,
            'do_value_by_outlet'  => $doValueByOutlet,
            'carryover_by_outlet' => $carryoverByOutlet->map(fn ($q, $id) => ['outlet_id' => $id, 'qty' => (int) $q])->values(),
            'piutang_do'          => $piutangDO,
            'external'            => ['in' => $externalIn, 'out' => $externalOut, 'net' => $externalNet],
            'daily_cash'          => $dailyCash,
            'daily_bank'          => $dailyBank,
            'alur_dana'           => $alurDana,
            'days_in_month'       => $daysInMonth,
        ];
    }
}
