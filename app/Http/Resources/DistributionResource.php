<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'period_id'   => $this->period_id,
            'courier_id'  => $this->courier_id,
            'courier_name' => $this->whenLoaded('courier', fn () => $this->courier?->name),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'dist_date'   => $this->dist_date?->toDateString(),
            'qty'         => (int) $this->qty,
            'price_per_unit' => (int) $this->price_per_unit,
            'total_value' => (int) ($this->total_value ?? $this->totalValue()),
            'paid_amount' => (int) $this->paid_amount,
            'remaining_amount' => (int) $this->remainingAmount(),
            'payment_status'   => $this->payment_status,
            'notes'       => $this->notes,
            'is_contract' => (bool) $this->is_contract,
            'contract_note' => $this->contract_note,
        ];
    }
}
