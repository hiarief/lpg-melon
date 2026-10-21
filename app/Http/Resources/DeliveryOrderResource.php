<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'period_id'   => $this->period_id,
            'outlet_id'   => $this->outlet_id,
            'outlet_name' => $this->whenLoaded('outlet', fn () => $this->outlet?->name),
            'do_date'     => $this->do_date?->toDateString(),
            'qty'         => (int) $this->qty,
            'price_per_unit' => (int) $this->price_per_unit,
            'total_value' => (int) ($this->total_value ?? $this->totalValue()),
            'paid_amount' => (int) $this->paid_amount,
            'remaining_amount' => (int) $this->remainingAmount(),
            'payment_status'   => $this->payment_status,
            'notes'       => $this->notes,
            'is_carryover' => str_contains($this->notes ?? '', 'Carry-over'),
        ];
    }
}
