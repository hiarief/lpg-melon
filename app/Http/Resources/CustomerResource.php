<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'type'     => $this->type,
            'outlet_id'   => $this->outlet_id,
            'outlet_name' => $this->whenLoaded('outlet', fn () => $this->outlet?->name),
            'is_active'   => (bool) $this->is_active,
        ];
    }
}
