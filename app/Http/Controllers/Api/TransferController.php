<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourierDepositResource;
use App\Http\Resources\AccountTransferResource;
use App\Models\AccountTransfer;
use App\Models\CourierDeposit;
use App\Models\DeliveryOrder;
use App\Models\Period;
use App\Models\Saving;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $deposits = CourierDeposit::with('courier')->where('period_id', $period->id)
            ->orderBy('deposit_date')->get();
        $transfers = AccountTransfer::with('deliveryOrders.outlet')->where('period_id', $period->id)
            ->orderBy('transfer_date')->get();

        [$unpaidDOs, $prevUnpaidDOs, $allUnpaidDOs] = $this->resolveUnpaidDOs($period);

        return $this->ok([
            'period_id' => $period->id,
            'deposits'  => CourierDepositResource::collection($deposits),
            'transfers' => AccountTransferResource::collection($transfers),
            'unpaid_dos'  => $this->mapDOs($unpaidDOs),
            'prev_unpaid_dos' => $this->mapDOs($prevUnpaidDOs),
            'all_unpaid_dos'  => $this->mapDOs($allUnpaidDOs),
            'totals' => [
                'deposited'       => (int) $deposits->sum('amount'),
                'admin_fees'      => (int) $deposits->sum('admin_fee'),
                'transferred'     => (int) $transfers->sum('amount'),
                'transfer_qty'    => (int) $transfers->sum('do_equivalent_qty'),
                'surplus'         => (int) $transfers->sum('surplus'),
                'piutang_do'      => (int) $allUnpaidDOs->sum(fn ($d) => $d->remainingAmount()),
                'penampung_now'   => (int) ($period->opening_penampung
                    + $deposits->sum('amount')
                    - $transfers->sum('amount')
                    - $deposits->sum('admin_fee')),
            ],
        ]);
    }

    public function storeDeposit(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'courier_id'   => 'required|exists:couriers,id',
            'deposit_date' => 'required|date',
            'amount'       => 'required|integer|min:1',
            'admin_fee'    => 'nullable|integer|min:0',
            'reference_no' => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        return $this->ok(new CourierDepositResource(CourierDeposit::create($data + ['period_id' => $period->id])), 201);
    }

    public function destroyDeposit(CourierDeposit $deposit)
    {
        $deposit->delete();

        return $this->ok(['deleted' => true]);
    }

    public function storeTransfer(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'transfer_date'   => 'required|date',
            'amount'          => 'required|integer|min:1',
            'notes'           => 'nullable|string',
            'alloc_mode'      => 'required|in:auto,manual',
            'do_allocations'  => 'nullable|array',
            'do_allocations.*.do_id'  => 'required_if:alloc_mode,manual|exists:delivery_orders,id',
            'do_allocations.*.amount' => 'required_if:alloc_mode,manual|integer|min:1',
        ]);

        // period_id berasal dari query string (resolvePeriod), bukan dari body
        $data['period_id'] = $period->id;

        $result = DB::transaction(function () use ($data) {
            $transfer = AccountTransfer::create([
                'period_id'         => $data['period_id'],
                'transfer_date'     => $data['transfer_date'],
                'amount'            => $data['amount'],
                'do_equivalent_qty' => 0,
                'surplus'           => 0,
                'notes'             => $data['notes'] ?? null,
            ]);

            [$qty, $surplus] = $data['alloc_mode'] === 'auto'
                ? $this->allocateAuto($transfer, (int) $data['amount'], (int) $data['period_id'])
                : $this->allocateManual($transfer, (int) $data['amount'], $data['do_allocations'] ?? []);

            $transfer->update(['do_equivalent_qty' => $qty, 'surplus' => $surplus]);

            if ($surplus > 0) {
                Saving::create([
                    'period_id'           => (int) $data['period_id'],
                    'account_transfer_id' => $transfer->id,
                    'entry_date'          => $data['transfer_date'],
                    'type'                => 'in',
                    'amount'              => $surplus,
                    'description'         => 'Surplus transfer ' . $data['transfer_date']
                        . (isset($data['notes']) ? ' — ' . $data['notes'] : ''),
                ]);
            }

            return $transfer->fresh(['deliveryOrders.outlet']);
        });

        return $this->ok(new AccountTransferResource($result), 201);
    }

    public function destroyTransfer(AccountTransfer $transfer)
    {
        DB::transaction(function () use ($transfer) {
            $doIds = $transfer->deliveryOrders()->pluck('delivery_orders.id');
            $transfer->deliveryOrders()->detach();
            Saving::where('account_transfer_id', $transfer->id)->delete();
            $transfer->delete();

            DeliveryOrder::whereIn('id', $doIds)->get()->each->recalcPayment();
        });

        return $this->ok(['deleted' => true]);
    }

    // ── HELPERS ───────────────────────────────────────────────────────────

    private function allocateAuto(AccountTransfer $transfer, int $amount, int $periodId): array
    {
        $remaining  = $amount;
        $doEquivQty = 0;

        $doList = DeliveryOrder::with('outlet')
            ->whereHas('period', fn ($q) => $q->where('id', '<=', $periodId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderByRaw("CASE WHEN notes LIKE '%Carry-over%' THEN 0 ELSE 1 END")
            ->orderBy('do_date')->orderBy('id')->get();

        foreach ($doList as $do) {
            if ($remaining <= 0) break;
            $alloc = min($remaining, $do->remainingAmount());
            if ($alloc <= 0) continue;

            $transfer->deliveryOrders()->attach($do->id, ['amount_allocated' => $alloc]);
            $remaining -= $alloc;
            $doEquivQty += intdiv($alloc, max(1, (int) $do->price_per_unit));
            $do->recalcPayment();
        }

        return [$doEquivQty, max(0, $remaining)];
    }

    private function allocateManual(AccountTransfer $transfer, int $amount, array $allocations): array
    {
        $remaining  = $amount;
        $doEquivQty = 0;

        foreach ($allocations as $alloc) {
            $do = DeliveryOrder::find($alloc['do_id'] ?? null);
            if (! $do) continue;

            $allocAmount = min((int) ($alloc['amount'] ?? 0), $do->remainingAmount());
            if ($allocAmount <= 0) continue;

            $transfer->deliveryOrders()->attach($do->id, ['amount_allocated' => $allocAmount]);
            $remaining  -= $allocAmount;
            $doEquivQty += intdiv($allocAmount, max(1, (int) $do->price_per_unit));
            $do->recalcPayment();
        }

        return [$doEquivQty, max(0, $remaining)];
    }

    private function resolveUnpaidDOs(Period $period): array
    {
        $unpaidDOs = DeliveryOrder::with('outlet')->where('period_id', $period->id)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('do_date')->get();

        $carryoverDOs = DeliveryOrder::with(['outlet', 'period'])->where('period_id', $period->id)
            ->where('notes', 'like', '%Carry-over%')
            ->whereIn('payment_status', ['unpaid', 'partial'])->get();

        $carriedOutlets = $carryoverDOs->pluck('outlet_id')->unique();

        [$prevMonth, $prevYear] = $period->month === 1
            ? [12, $period->year - 1]
            : [$period->month - 1, $period->year];
        $prevPeriod = Period::where('year', $prevYear)->where('month', $prevMonth)->first();

        $prevUnpaidDOs = $prevPeriod
            ? DeliveryOrder::with(['outlet', 'period'])->where('period_id', $prevPeriod->id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotIn('outlet_id', $carriedOutlets)
                ->orderBy('do_date')->get()
            : collect();

        return [$unpaidDOs, $prevUnpaidDOs, $prevUnpaidDOs->concat($carryoverDOs)->concat($unpaidDOs)];
    }

    private function mapDOs($dos): array
    {
        return $dos->map(fn ($d) => [
            'id' => $d->id,
            'outlet_id' => $d->outlet_id,
            'outlet_name' => $d->outlet?->name,
            'do_date' => $d->do_date?->toDateString(),
            'period_label' => $d->period?->label,
            'qty' => (int) $d->qty,
            'price_per_unit' => (int) $d->price_per_unit,
            'value' => (int) $d->totalValue(),
            'paid' => (int) $d->paid_amount,
            'remaining' => (int) $d->remainingAmount(),
            'status' => $d->payment_status,
            'is_carryover' => str_contains($d->notes ?? '', 'Carry-over'),
        ])->values()->all();
    }
}
