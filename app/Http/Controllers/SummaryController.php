<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\Courier;
use App\Models\CourierDeposit;
use App\Models\DailyExpense;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\ExternalDebt;
use App\Models\Outlet;
use App\Models\OutletContractPayment;
use App\Models\Period;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();
        $outlets = Outlet::where('is_active', true)->get();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);

        $dayTotals      = array_fill(1, $daysInMonth, 0);

        // ─── STOK ───────────────────────────────────────────────────────
        // DO murni bulan ini (EXCLUDE carry-over — carry-over sudah ada di opening_stock)
        $doByOutlet = DeliveryOrder::where('period_id', $period->id)->where(fn($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))->selectRaw('outlet_id, SUM(qty) as total_qty')->groupBy('outlet_id')->pluck('total_qty', 'outlet_id');
        $doBayar = DeliveryOrder::where('period_id', $period->id)->where(fn($q) => $q->where('payment_status', 'paid')->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))->selectRaw('outlet_id, SUM(qty) as total_qty')->groupBy('outlet_id')->pluck('total_qty', 'outlet_id');
        $doBayarByOutlet = $doBayar->map(fn($qty) => $qty * 16000); // konversi ke nominal bayar (asumsi harga per unit 16k)
        // DO carry-over (ditampilkan terpisah sebagai info, BUKAN stok masuk)
        $doCarryoverByOutlet = DeliveryOrder::where('period_id', $period->id)->where('notes', 'like', '%Carry-over%')->selectRaw('outlet_id, SUM(qty) as total_qty')->groupBy('outlet_id')->pluck('total_qty', 'outlet_id');

        $totalDoQty = $doByOutlet->sum(); // DO baru bulan ini saja
        $totalCarryoverQty = $doCarryoverByOutlet->sum(); // piutang bawaan (info)
        $totalDistQty = Distribution::where('period_id', $period->id)->sum('qty');

        // Stok: sisa bulan lalu + DO baru bulan ini - distribusi
        // Carry-over TIDAK dihitung karena itu piutang pembayaran, bukan stok fisik baru
        $stockSisa = $period->opening_stock + $totalDoQty - $totalDistQty;

        // ─── PENJUALAN ──────────────────────────────────────────────────
        $salesByCustomer = Distribution::with('customer')->where('period_id', $period->id)->selectRaw('customer_id, SUM(qty) as total_qty, SUM(qty * price_per_unit) as total_value, SUM(paid_amount) as total_paid')->groupBy('customer_id')->get();

        $totalSales = $salesByCustomer->sum('total_value');
        $totalModal = $totalDistQty * 16000;
        $grossMargin = $totalSales - $totalModal;

        // ─── CASHFLOW HARIAN ────────────────────────────────────────────
        $expensesByCategory = DailyExpense::where('period_id', $period->id)->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category');

        $totalExpense = $expensesByCategory->sum();
        $netCashflow = $period->opening_cash + $totalSales - $totalExpense;


        // Surplus per transfer (tabungan di rek utama, dipecah per tanggal)
        $surplusTransfers = AccountTransfer::where('period_id', $period->id)
            ->where('surplus', '>', 0)
            ->orderBy('transfer_date')
            ->get(['transfer_date', 'amount', 'surplus', 'notes']);
        $totalSurplus = $surplusTransfers->sum('surplus');

        // ─── PIUTANG DO ──────────────────────────────────────────────────
        // Piutang DO bulan ini (non-carry-over)
        $unpaidDOs = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->where(fn($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get();
        $unpaidDoValue = $unpaidDOs->sum(fn($d) => $d->remainingAmount());

        // Piutang carry-over bulan ini yang belum lunas
        $carryoverUnpaidDOs = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->where('notes', 'like', '%Carry-over%')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get();
        $carryoverUnpaidValue = $carryoverUnpaidDOs->sum(fn($d) => $d->remainingAmount());

        // DO dari periode-periode sebelumnya yang masih belum lunas (bukan carry-over)
        $prevUnpaidDOs = DeliveryOrder::with(['outlet', 'period'])
            ->whereHas('period', fn($q) => $q->where('id', '<', $period->id))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get();
        $prevUnpaidValue = $prevUnpaidDOs->sum(fn($d) => $d->remainingAmount());

        // ─── KONTRAK PANGKALAN ───────────────────────────────────────────
        $contractPayments = OutletContractPayment::with('outlet')->where('period_id', $period->id)->get();

        // ─── GAJI KURIR ──────────────────────────────────────────────────
        $couriers = Courier::where('is_active', true)->get();
        $courierWages = [];
        foreach ($couriers as $c) {
            $qty = Distribution::where('period_id', $period->id)->where('courier_id', $c->id)->sum('qty');
            $courierWages[$c->id] = [
                'name' => $c->name,
                'qty' => $qty,
                'wage' => $qty * $c->wage_per_unit,
            ];
        }
        $depositsByDay = CourierDeposit::where('period_id', $period->id)
            ->selectRaw('DAY(deposit_date) as day, SUM(amount) as total, SUM(admin_fee) as total_admin')
            ->groupBy('day')->get()->keyBy('day')
            ->map(fn($r) => ['total' => (int)$r->total, 'admin' => (int)$r->total_admin])->toArray();

        $totalDeposits  = array_sum(array_column($depositsByDay, 'total'));
        $totalAdminFees = array_sum(array_column($depositsByDay, 'admin'));

        $salesByDay = Distribution::where('period_id', $period->id)
            ->selectRaw('DAY(dist_date) as day, SUM(paid_amount) as total')
            ->groupBy('day')->pluck('total', 'day')->toArray();
        $openingCash      = (int) $period->opening_cash;
        $runningCash  = $openingCash;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $runningCash += ($salesByDay[$day] ?? 0)
                - ($dayTotals[$day] ?? 0)
                - ($depositsByDay[$day]['total'] ?? 0)
                - ($depositsByDay[$day]['admin'] ?? 0);
            $dailyBalance[$day] = $runningCash;
        }

        // ─── PENAMPUNG & TRANSFER ────────────────────────────────────────
        $totalDeposited = CourierDeposit::where('period_id', $period->id)->sum('amount') + $totalAdminFees; // total setoran kurir + total admin fee (jika ada)
        $totalTransferred = AccountTransfer::where('period_id', $period->id)->sum('amount');
        $penampungBalance = $period->opening_penampung + $totalDeposited - $totalTransferred;

        $totalIncome  = array_sum($salesByDay);
        // ─── EXTERNAL DEBT ────────────────────────────────────────────────
        $externalIn = ExternalDebt::where('period_id', $period->id)->where('type', 'in')->sum('amount');
        $externalOut = ExternalDebt::where('period_id', $period->id)->where('type', 'out')->sum('amount');
        $externalNet = $period->opening_external_debt + $externalIn - $externalOut;

         // ── PIUTANG DO ─────────────────────────────────────────────────
        // DO bulan ini belum lunas (carry-over didahulukan)
        $unpaidDOs = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderByRaw("CASE WHEN notes LIKE '%Carry-over%' THEN 0 ELSE 1 END")
            ->orderBy('do_date')
            ->get();

        // DO dari periode sebelumnya yang masih belum lunas
        $prevUnpaidDOs = DeliveryOrder::with(['outlet', 'period'])
            ->whereHas('period', fn($q) => $q->where('id', '<', $period->id))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('do_date')
            ->get();

        // Gabungkan: carry-over & prev dulu, baru bulan ini (urutan pelunasan)
        $allUnpaidDOs = $prevUnpaidDOs->concat($unpaidDOs->filter(fn($d) => str_contains($d->notes ?? '', 'Carry-over')))->concat($unpaidDOs->filter(fn($d) => !str_contains($d->notes ?? '', 'Carry-over')));

        $piutangDOTotal = $allUnpaidDOs->sum(fn($d) => $d->remainingAmount());


        $transfers = AccountTransfer::with('deliveryOrders.outlet')->where('period_id', $period->id)->orderBy('transfer_date')->get();

        $grandBayarDO = $transfers->sum('amount');
        return view('summary.index', compact('period', 'periods', 'outlets', 'doByOutlet', 'doCarryoverByOutlet', 'totalDoQty', 'totalCarryoverQty', 'totalDistQty', 'stockSisa', 'salesByCustomer', 'totalSales', 'totalModal', 'grossMargin', 'expensesByCategory', 'totalExpense', 'netCashflow', 'totalDeposited', 'totalTransferred', 'penampungBalance', 'surplusTransfers', 'totalSurplus', 'unpaidDOs', 'unpaidDoValue', 'carryoverUnpaidDOs', 'carryoverUnpaidValue', 'prevUnpaidDOs', 'prevUnpaidValue', 'contractPayments', 'courierWages', 'externalIn', 'externalOut', 'externalNet','totalIncome','totalDeposits','totalAdminFees', 'piutangDOTotal','grandBayarDO','doBayarByOutlet'));
    }
}