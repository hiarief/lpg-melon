<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Outlet;
use App\Models\OutletContractPayment;
use App\Models\Period;
use Illuminate\Http\Request;

class DeliveryOrderController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    //  INDEX
    // ──────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $period  = Period::findOrFail($request->period_id ?? Period::current()?->id);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();
        $outlets = Outlet::where('is_active', true)->get();

        // DO murni bulan ini (exclude carry-over)
        $dos = DeliveryOrder::with(['outlet', 'transfers'])
            ->where('period_id', $period->id)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->orderBy('do_date')
            ->get();

        // DO carry-over (piutang bawaan bulan lalu)
        $carryoverDOs = DeliveryOrder::with(['outlet', 'transfers'])
            ->where('period_id', $period->id)
            ->where('notes', 'like', '%Carry-over%')
            ->orderBy('do_date')
            ->get();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);
        $doByDate    = $dos->groupBy(fn ($d) => $d->do_date->format('Y-m-d'));

        // Detail per hari untuk modal "Rekap DO per Tanggal"
        $doDayDetail = $this->buildDayDetail($dos, $period, $daysInMonth);

        // Periode & DO bulan sebelumnya (untuk proyeksi)
        [$prevMonth, $prevYear] = $period->month === 1
            ? [12, $period->year - 1]
            : [$period->month - 1, $period->year];

        $prevPeriod = Period::where('year', $prevYear)->where('month', $prevMonth)->first();

        $prevDOs = $prevPeriod
            ? DeliveryOrder::with('outlet')
                ->where('period_id', $prevPeriod->id)
                ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
                ->get()
            : collect();

        return view('do.index', compact(
            'period',
            'periods',
            'outlets',
            'dos',
            'carryoverDOs',
            'doByDate',
            'doDayDetail',
            'daysInMonth',
            'prevPeriod',
            'prevDOs',
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  DETAIL PER TANGGAL × PANGKALAN (untuk modal Rekap DO per Tanggal)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Bangun rincian per sel (tanggal × pangkalan): DO apa saja, transfer yang
     * melunasi, sisa yang harus dibayar, dan surplus transfer.
     *
     * Kunci: "Y-m-d|outlet_id"
     *
     * @return array<string, array>
     */
    private function buildDayDetail($dos, Period $period, int $daysInMonth): array
    {
        $doIds = $dos->pluck('id')->all();

        // ── Transfer yang melunasi DO periode ini ────────────────────────────
        // TIDAK difilter per period_id: transfer bulan ini bisa melunasi DO
        // bulan lalu, dan sebaliknya. Surplus mengikuti TANGGAL DO, bukan
        // tanggal transfer — jadi kita butuh semua transfer yang menyentuh
        // DO di grid ini, dari periode mana pun.
        $linkedTransfers = \App\Models\AccountTransfer::with('deliveryOrders')
            ->whereHas('deliveryOrders', fn ($q) => $q->whereIn('delivery_orders.id', $doIds))
            ->orderBy('transfer_date')
            ->get();

        // ── Peta surplus per sel (tanggal DO × pangkalan) ────────────────────
        // allocateAuto() melunasi DO TERLAMA dulu, sehingga dana sisa (surplus)
        // baru muncul setelah DO terakhir yang dilunasi. Karena itu surplus
        // dibebankan ke tanggal DO terakhir tersebut.
        $surplusByCell = [];
        $paymentsByDo  = [];

        foreach ($linkedTransfers as $t) {
            $paidDOs = $t->deliveryOrders;
            if ($paidDOs->isEmpty()) continue;

            $last       = $paidDOs->sortBy(fn ($d) => $d->do_date->format('Y-m-d'))->last();
            $lastInGrid = in_array($last->id, $doIds, true);
            $surplus    = (int) $t->surplus;

            if ($lastInGrid && $surplus > 0) {
                $cellKey = $last->do_date->format('Y-m-d') . '|' . $last->outlet_id;
                $surplusByCell[$cellKey] = ($surplusByCell[$cellKey] ?? 0) + $surplus;
            }

            // Rincian pembayaran per DO (hanya DO yang ada di grid ini)
            foreach ($paidDOs as $d) {
                if (! in_array($d->id, $doIds, true)) continue;

                $paymentsByDo[$d->id][] = [
                    'id'      => $t->id,
                    'dateFmt' => $t->transfer_date->locale('id')->translatedFormat('d M Y'),
                    'alloc'   => (int) $d->pivot->amount_allocated,
                    // surplus ditandai pada DO TERAKHIR yang dilunasi
                    'surplus' => ($lastInGrid && $last->id === $d->id) ? $surplus : 0,
                ];
            }
        }

        $detail = [];

        // Kelompokkan DO per tanggal, lalu per pangkalan
        foreach ($dos->groupBy(fn ($d) => $d->do_date->format('Y-m-d')) as $dateStr => $dayDOs) {
            $day       = (int) substr($dateStr, -2);
            $dateFmt   = \Carbon\Carbon::parse($dateStr)->locale('id')->translatedFormat('d F Y');
            $dateShort = \Carbon\Carbon::parse($dateStr)->locale('id')->translatedFormat('d M Y');

            foreach ($dayDOs->groupBy('outlet_id') as $outletId => $rows) {
                $cellKey    = $dateStr . '|' . $outletId;
                $outletName = $rows->first()->outlet->name ?? '-';

                $doRows = $rows->map(function ($d) use ($paymentsByDo) {
                    $total = $d->totalValue();
                    $paid  = (int) $d->paid_amount;

                    return [
                        'id'       => $d->id,
                        'qty'      => (int) $d->qty,
                        'price'    => (int) $d->price_per_unit,
                        'total'    => $total,
                        'paid'     => $paid,
                        'sisa'     => max(0, $total - $paid),
                        'status'   => $d->payment_status,
                        'notes'    => $d->notes,
                        'payments' => $paymentsByDo[$d->id] ?? [],
                    ];
                })->values()->all();

                $detail[$cellKey] = [
                    'key'       => $cellKey,
                    'day'       => $day,
                    'date'      => $dateStr,
                    'dateFmt'   => $dateFmt,
                    'dateShort' => $dateShort,
                    'outlet'    => $outletName,
                    'dos'       => $doRows,
                    'qty'       => (int) $rows->sum('qty'),
                    'nilai'     => (int) $rows->sum(fn ($d) => $d->qty * $d->price_per_unit),
                    'bayar'     => (int) $rows->sum('paid_amount'),
                    'sisa'      => (int) $rows->sum(fn ($d) => max(0, $d->totalValue() - $d->paid_amount)),
                    // Surplus menurut TANGGAL DO
                    'surplus'   => (int) ($surplusByCell[$cellKey] ?? 0),
                    'status'    => $this->dayPaymentStatus($rows),
                ];
            }
        }

        return $detail;
    }

    /** Status pelunasan satu sel: 'lunas' | 'sebagian' | 'belum' */
    private function dayPaymentStatus($rows): string
    {
        if ($rows->isEmpty()) {
            return 'kosong';
        }

        $total = $rows->sum(fn ($d) => $d->totalValue());
        $paid  = (int) $rows->sum('paid_amount');

        if ($total <= 0)     return 'kosong';
        if ($paid >= $total) return 'lunas';
        if ($paid > 0)       return 'sebagian';

        return 'belum';
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CREATE / STORE
    // ──────────────────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $period  = Period::findOrFail($request->period_id ?? Period::current()?->id);
        $outlets = Outlet::where('is_active', true)->get();

        return view('do.create', compact('period', 'outlets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'period_id'      => 'required|exists:periods,id',
            'outlet_id'      => 'required|exists:outlets,id',
            'do_date'        => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:1000',
            'notes'          => 'nullable|string',
        ]);

        $period = Period::findOrFail($request->period_id);

        if ($period->status === 'closed') {
            return back()->with('error', 'Periode sudah ditutup.');
        }

        DeliveryOrder::create(
            $request->only('period_id', 'outlet_id', 'do_date', 'qty', 'price_per_unit', 'notes')
            + ['payment_status' => 'unpaid', 'paid_amount' => 0]
        );

        $outlet = Outlet::find($request->outlet_id);

        if ($outlet->contract_type === 'per_do') {
            $this->recalcContractPayment($period, $outlet);
        }

        return redirect()
            ->route('do.index', ['period_id' => $request->period_id])
            ->with('success', 'DO berhasil disimpan.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  EDIT / UPDATE
    // ──────────────────────────────────────────────────────────────────────────

    public function edit(DeliveryOrder $do)
    {
        $outlets = Outlet::where('is_active', true)->get();

        return view('do.edit', compact('do', 'outlets'));
    }

    public function update(Request $request, DeliveryOrder $do)
    {
        $request->validate([
            'do_date'        => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:1000',
            'notes'          => 'nullable|string',
        ]);

        $do->update($request->only('do_date', 'qty', 'price_per_unit', 'notes'));
        $do->recalcPayment();

        if ($do->outlet->contract_type === 'per_do') {
            $this->recalcContractPayment($do->period, $do->outlet);
        }

        return redirect()
            ->route('do.index', ['period_id' => $do->period_id])
            ->with('success', 'DO berhasil diupdate.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  DESTROY
    // ──────────────────────────────────────────────────────────────────────────

    public function destroy(DeliveryOrder $do)
    {
        $periodId = $do->period_id;
        $outlet   = $do->outlet;
        $period   = $do->period;

        $do->delete();

        if ($outlet->contract_type === 'per_do') {
            $this->recalcContractPayment($period, $outlet);
        }

        return redirect()
            ->route('do.index', ['period_id' => $periodId])
            ->with('success', 'DO dihapus.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function recalcContractPayment(Period $period, Outlet $outlet): void
    {
        $totalQty = DeliveryOrder::where('period_id', $period->id)
            ->where('outlet_id', $outlet->id)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->sum('qty');

        $payment = OutletContractPayment::firstOrCreate(
            ['period_id' => $period->id, 'outlet_id' => $outlet->id],
            ['paid_amount' => 0, 'status' => 'unpaid']
        );

        $payment->calculated_amount = $totalQty * $outlet->contract_rate;
        $payment->save();
    }
}