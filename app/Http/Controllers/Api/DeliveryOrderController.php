<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Models\OutletContractPayment;
use App\Models\Period;
use Illuminate\Http\Request;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $includeCarryover = filter_var($request->query('include_carryover', false), FILTER_VALIDATE_BOOLEAN);

        $query = DeliveryOrder::with(['outlet', 'transfers'])
            ->where('period_id', $period->id);

        if (! $includeCarryover) {
            $query->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'));
        }

        $dos = $query->orderBy('do_date')->orderBy('id')->get();

        $carryover = DeliveryOrder::with(['outlet', 'transfers'])
            ->where('period_id', $period->id)
            ->where('notes', 'like', '%Carry-over%')
            ->orderBy('do_date')->get();

        // Rincian per sel (tanggal DO × pangkalan) untuk tampilan grid di aplikasi
        $cells = $this->buildCells($dos, $carryover, $period);

        return $this->ok([
            'period_id' => $period->id,
            'dos'       => DeliveryOrderResource::collection($dos),
            'carryover' => DeliveryOrderResource::collection($carryover),
            'cells'     => $cells,
            'totals'    => [
                'qty'     => (int) $dos->sum('qty'),
                'value'   => (int) $dos->sum(fn ($d) => $d->totalValue()),
                'paid'    => (int) $dos->sum('paid_amount'),
                'remaining' => (int) $dos->sum(fn ($d) => $d->remainingAmount()),
                'carryover_qty' => (int) $carryover->sum('qty'),
            ],
        ]);
    }

    private function buildCells($dos, $carryover, Period $period): array
    {
        $grouped = $dos->groupBy(fn ($d) => $d->do_date->format('Y-m-d') . '|' . $d->outlet_id);

        return $grouped->map(function ($rows) {
            $total = (int) $rows->sum(fn ($d) => $d->totalValue());
            $paid  = (int) $rows->sum('paid_amount');

            $status = $total <= 0 ? 'kosong'
                : ($paid >= $total ? 'lunas' : ($paid > 0 ? 'sebagian' : 'belum'));

            return [
                'date'   => $rows->first()->do_date->format('Y-m-d'),
                'outlet_id' => $rows->first()->outlet_id,
                'outlet_name' => $rows->first()->outlet?->name,
                'qty'    => (int) $rows->sum('qty'),
                'value'  => $total,
                'paid'   => $paid,
                'remaining' => (int) $rows->sum(fn ($d) => $d->remainingAmount()),
                'surplus' => 0,
                'status' => $status,
                'dos'    => DeliveryOrderResource::collection($rows)->resolve(),
            ];
        })->values()->all();
    }

    public function store(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'outlet_id'      => 'required|exists:outlets,id',
            'do_date'        => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:1000',
            'notes'          => 'nullable|string',
        ]);

        $do = DeliveryOrder::create($data + [
            'period_id'      => $period->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
        ]);

        $outlet = $do->outlet;
        if ($outlet && $outlet->contract_type === 'per_do') {
            $this->recalcContractPayment($period, $outlet);
        }

        return $this->ok(new DeliveryOrderResource($do->load('outlet')), 201);
    }

    public function update(Request $request, DeliveryOrder $do)
    {
        $data = $request->validate([
            'do_date'        => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:1000',
            'notes'          => 'nullable|string',
        ]);

        $do->update($data);
        $do->recalcPayment();

        if ($do->outlet && $do->outlet->contract_type === 'per_do') {
            $this->recalcContractPayment($do->period, $do->outlet);
        }

        return $this->ok(new DeliveryOrderResource($do->load('outlet')));
    }

    public function destroy(DeliveryOrder $do)
    {
        $period = $do->period;
        $outlet = $do->outlet;

        $do->delete();

        if ($outlet && $outlet->contract_type === 'per_do') {
            $this->recalcContractPayment($period, $outlet);
        }

        return $this->ok(['deleted' => true]);
    }

    private function recalcContractPayment(Period $period, $outlet): void
    {
        $totalQty = DeliveryOrder::where('period_id', $period->id)
            ->where('outlet_id', $outlet->id)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->sum('qty');

        $payment = OutletContractPayment::firstOrCreate(
            ['period_id' => $period->id, 'outlet_id' => $outlet->id],
            ['paid_amount' => 0, 'status' => 'unpaid']
        );

        $payment->calculated_amount = $totalQty * (int) $outlet->contract_rate;
        $payment->save();
    }
}
