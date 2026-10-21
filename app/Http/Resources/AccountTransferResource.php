<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidDOs = $this->whenLoaded('deliveryOrders', fn () => $this->deliveryOrders);

        return [
            'id'          => $this->id,
            'period_id'   => $this->period_id,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'amount'      => (int) $this->amount,
            'do_equivalent_qty' => (int) $this->do_equivalent_qty,
            'surplus'     => (int) $this->surplus,
            'notes'       => $this->notes,
            'allocated_dos' => $paidDOs
                ? $paidDOs->map(fn ($d) => [
                    'do_id'     => $d->id,
                    'outlet_name' => $d->outlet?->name,
                    'do_date'   => $d->do_date?->toDateString(),
                    'allocated' => (int) ($d->pivot->amount_allocated ?? 0),
                ])->values()->all()
                : [],
        ];
    }
}
