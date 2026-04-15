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
            'daysInMonth',
            'prevPeriod',
            'prevDOs',
        ));
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