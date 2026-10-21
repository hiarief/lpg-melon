<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasTransfer = $this->resource->relationLoaded('accountTransfer') && $this->accountTransfer !== null;
        $transfer = $this->accountTransfer;

        return [
            'id'          => $this->id,
            'period_id'   => $this->period_id,
            'account_transfer_id' => $this->account_transfer_id,
            'entry_date'  => $this->entry_date?->toDateString(),
            'type'        => $this->type,           // 'in' | 'out'
            'amount'      => (int) $this->amount,
            'description' => $this->description,
            // Dipakai untuk tab "Mutasi Tabungan per Tanggal DO"
            'transfer_date' => $hasTransfer ? $transfer->transfer_date?->toDateString() : null,
            'earliest_do_date' => $hasTransfer && $transfer->deliveryOrders->isNotEmpty()
                ? optional($transfer->deliveryOrders->sortBy('do_date')->first())->do_date?->toDateString()
                : null,
            'do_qty' => $hasTransfer ? (int) $transfer->deliveryOrders->sum('qty') : 0,
        ];
    }
}
