<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalDebtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'period_id'    => $this->period_id,
            'entry_date'   => $this->entry_date?->toDateString(),
            'type'         => $this->type,      // 'in' | 'out'
            'source_name'  => $this->source_name,
            'amount'       => (int) $this->amount,
            'description'  => $this->description,
        ];
    }
}
