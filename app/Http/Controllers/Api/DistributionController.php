<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DistributionResource;
use App\Models\Distribution;
use Illuminate\Http\Request;

class DistributionController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $distributions = Distribution::with(['customer', 'courier'])->where('period_id', $period->id)
            ->orderBy('dist_date')->orderBy('customer_id')->get();

        $byCustomer = Distribution::with('customer')->where('period_id', $period->id)
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
                ];
            })->sortByDesc('qty')->values()->all();

        $byCourier = Distribution::with('courier')->where('period_id', $period->id)
            ->selectRaw('courier_id, SUM(qty) as qty')->groupBy('courier_id')
            ->pluck('qty', 'courier_id')->map(fn ($v) => (int) $v);

        return $this->ok([
            'period_id'  => $period->id,
            'distributions' => DistributionResource::collection($distributions),
            'by_customer'   => $byCustomer,
            'by_courier'    => $byCourier,
            'totals' => [
                'qty'   => (int) $distributions->sum('qty'),
                'value' => (int) $distributions->sum(fn ($d) => $d->totalValue()),
                'paid'  => (int) $distributions->sum('paid_amount'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'courier_id'     => 'required|exists:couriers,id',
            'customer_id'    => 'required|exists:customers,id',
            'dist_date'      => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'nullable|integer|min:0',
            'payment_status' => 'nullable|in:paid,deferred,partial',
            'paid_amount'    => 'nullable|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        return $this->ok(new DistributionResource(Distribution::create($data + ['period_id' => $period->id])), 201);
    }

    public function bulkStore(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $rows = $request->validate([
            'items'               => 'required|array|min:1',
            'items.*.courier_id'  => 'required|exists:couriers,id',
            'items.*.customer_id' => 'required|exists:customers,id',
            'items.*.dist_date'   => 'required|date',
            'items.*.qty'         => 'required|integer|min:1',
            'items.*.price_per_unit' => 'nullable|integer|min:0',
            'items.*.paid_amount' => 'nullable|integer|min:0',
            'items.*.notes'       => 'nullable|string',
        ]);

        $created = 0;
        foreach ($rows['items'] as $item) {
            Distribution::create($item + ['period_id' => $period->id]);
            $created++;
        }

        return $this->ok(['created' => $created], 201);
    }

    public function recordPayment(Request $request, Distribution $distribution)
    {
        $data = $request->validate([
            'paid_amount'    => 'required|integer|min:0',
            'payment_status' => 'nullable|in:paid,deferred,partial',
        ]);

        $distribution->update($data);

        return $this->ok(new DistributionResource($distribution->fresh(['customer', 'courier'])));
    }

    public function update(Request $request, Distribution $distribution)
    {
        $data = $request->validate([
            'courier_id'     => 'required|exists:couriers,id',
            'customer_id'    => 'required|exists:customers,id',
            'dist_date'      => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'nullable|integer|min:0',
            'paid_amount'    => 'nullable|integer|min:0',
            'payment_status' => 'nullable|in:paid,deferred,partial',
            'notes'          => 'nullable|string',
        ]);

        $distribution->update($data);

        return $this->ok(new DistributionResource($distribution->fresh(['customer', 'courier'])));
    }

    public function destroy(Distribution $distribution)
    {
        $distribution->delete();

        return $this->ok(['deleted' => true]);
    }
}
