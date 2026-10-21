<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'wage_per_unit' => (int) $this->wage_per_unit,
            'is_active'     => (bool) $this->is_active,
        ];
    }
}
