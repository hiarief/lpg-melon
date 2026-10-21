<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourierDepositResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'period_id'    => $this->period_id,
            'courier_id'   => $this->courier_id,
            'courier_name' => $this->whenLoaded('courier', fn () => $this->courier?->name),
            'deposit_date' => $this->deposit_date?->toDateString(),
            'amount'       => (int) $this->amount,
            'admin_fee'    => (int) $this->admin_fee,
            'net_amount'   => (int) ($this->net_amount ?? ($this->amount + $this->admin_fee)),
            'reference_no' => $this->reference_no,
            'notes'        => $this->notes,
        ];
    }
}
