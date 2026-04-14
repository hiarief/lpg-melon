<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\Period;
use Illuminate\Http\Request;

class DistributionController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        $distributions = Distribution::with(['customer', 'courier'])
            ->where('period_id', $period->id)
            ->orderBy('dist_date')
            ->orderBy('customer_id')
            ->get();

        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);

        // Grid: customer -> day -> akumulasi (bisa ada lebih dari 1 baris per hari)
        $grid = [];
        foreach ($distributions as $d) {
            $day = $d->dist_date->day;
            $cid = $d->customer_id;
            if (!isset($grid[$cid][$day])) {
                $grid[$cid][$day] = [
                    'qty' => 0,
                    'total_value' => 0,
                    'paid_amount' => 0,
                    'payment_status' => $d->payment_status,
                    'ids' => [],
                ];
            }
            $grid[$cid][$day]['qty'] += $d->qty;
            $grid[$cid][$day]['total_value'] += $d->qty * $d->price_per_unit;
            $grid[$cid][$day]['paid_amount'] += $d->paid_amount;
            $grid[$cid][$day]['ids'][] = $d->id;
            // Status: jika salah satu deferred/partial, tampilkan itu
            if ($d->payment_status !== 'paid') {
                $grid[$cid][$day]['payment_status'] = $d->payment_status;
            }
        }

        // Per-customer totals (hitung manual karena total_value adalah generated column)
        $customerTotals = [];
        foreach ($customers as $c) {
            $rows = $distributions->where('customer_id', $c->id);
            $totalVal = $rows->sum(fn($d) => $d->qty * $d->price_per_unit);
            $customerTotals[$c->id] = [
                'qty' => $rows->sum('qty'),
                'total_value' => $totalVal,
                'paid' => $rows->sum('paid_amount'),
            ];
        }

        $couriers = Courier::where('is_active', true)->get();

        $doByOutlet = DeliveryOrder::where('period_id', $period->id)->where(fn($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))->selectRaw('outlet_id, SUM(qty) as total_qty')->groupBy('outlet_id')->pluck('total_qty', 'outlet_id');
        $totalDoQty = $doByOutlet->sum(); // DO baru bulan ini saja

        return view('distributions.index', compact('period', 'periods', 'distributions', 'customers', 'couriers', 'daysInMonth', 'grid', 'customerTotals','totalDoQty'));
    }

    public function create(Request $request)
    {
        $period = Period::findOrFail($request->period_id ?? Period::current()?->id);
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $couriers = Courier::where('is_active', true)->get();
        return view('distributions.create', compact('period', 'customers', 'couriers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id',
            'courier_id' => 'required|exists:couriers,id',
            'customer_id' => 'required|exists:customers,id',
            'dist_date' => 'required|date',
            'qty' => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:0|max:25000',
            'payment_status' => 'required|in:paid,deferred,partial',
            'paid_amount' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $period = Period::findOrFail($request->period_id);
        if ($period->status === 'closed') {
            return back()->with('error', 'Periode sudah ditutup.');
        }

        $paidAmount = $request->payment_status === 'paid' ? $request->qty * $request->price_per_unit : $request->paid_amount ?? 0;

        Distribution::create($request->only('period_id', 'courier_id', 'customer_id', 'dist_date', 'qty', 'price_per_unit', 'payment_status', 'notes') + ['paid_amount' => $paidAmount]);

        return redirect()
            ->route('distributions.index', ['period_id' => $request->period_id])
            ->with('success', 'Distribusi berhasil disimpan.');
    }

    /** Bulk store - input satu hari banyak customer sekaligus */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id',
            'courier_id' => 'required|exists:couriers,id',
            'dist_date' => 'required|date',
            'rows' => 'required|array|min:1',
            'rows.*.customer_id' => 'required|exists:customers,id',
            'rows.*.qty' => 'required|integer|min:1',
            'rows.*.price_per_unit' => 'required|integer|min:10000',
            'rows.*.payment_status' => 'required|in:paid,deferred,partial',
            'rows.*.paid_amount' => 'nullable|integer|min:0',
        ]);

        $period = Period::findOrFail($request->period_id);
        if ($period->status === 'closed') {
            return back()->with('error', 'Periode sudah ditutup.');
        }

        foreach ($request->rows as $row) {
            if (empty($row['qty']) || $row['qty'] < 1) {
                continue;
            }

            $paidAmount = $row['payment_status'] === 'paid' ? $row['qty'] * $row['price_per_unit'] : $row['paid_amount'] ?? 0;

            Distribution::create([
                'period_id' => $request->period_id,
                'courier_id' => $request->courier_id,
                'customer_id' => $row['customer_id'],
                'dist_date' => $request->dist_date,
                'qty' => $row['qty'],
                'price_per_unit' => $row['price_per_unit'],
                'payment_status' => $row['payment_status'],
                'paid_amount' => $paidAmount,
                'notes' => $row['notes'] ?? null,
            ]);
        }

        return redirect()
            ->route('distributions.index', ['period_id' => $request->period_id])
            ->with('success', 'Distribusi harian berhasil disimpan.');
    }

    public function edit(Distribution $distribution)
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $couriers = Courier::where('is_active', true)->get();
        return view('distributions.edit', compact('distribution', 'customers', 'couriers'));
    }

    public function update(Request $request, Distribution $distribution)
    {
        $request->validate([
            'dist_date' => 'required|date',
            'qty' => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:0',
            'payment_status' => 'required|in:paid,deferred,partial',
            'paid_amount' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $paidAmount = $request->payment_status === 'paid' ? $request->qty * $request->price_per_unit : $request->paid_amount ?? 0;

        $distribution->update($request->only('dist_date', 'qty', 'price_per_unit', 'courier_id', 'customer_id', 'payment_status', 'notes') + ['paid_amount' => $paidAmount]);

        return redirect()
            ->route('distributions.index', ['period_id' => $distribution->period_id])
            ->with('success', 'Distribusi diupdate.');
    }

    public function destroy(Distribution $distribution)
    {
        $periodId = $distribution->period_id;
        $distribution->delete();
        return redirect()
            ->route('distributions.index', ['period_id' => $periodId])
            ->with('success', 'Distribusi dihapus.');
    }

    /** Catat setoran dari customer kontrak (Angga/Sukmedi) */
    public function recordPayment(Request $request, Distribution $distribution)
    {
        $request->validate([
            'paid_amount' => 'required|integer|min:1',
        ]);

        $total = $distribution->qty * $distribution->price_per_unit;
        $newPaid = min($distribution->paid_amount + $request->paid_amount, $total);
        $status = $newPaid >= $total ? 'paid' : 'partial';

        $distribution->update(['paid_amount' => $newPaid, 'payment_status' => $status]);

        return back()->with('success', 'Setoran dicatat.');
    }
}
