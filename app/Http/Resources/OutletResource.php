<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'contract_type' => $this->contract_type,
            'contract_rate' => (int) $this->contract_rate,
            'is_active'     => (bool) $this->is_active,
        ];
    }
}
