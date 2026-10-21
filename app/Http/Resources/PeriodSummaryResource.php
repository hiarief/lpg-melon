<?php

namespace App\Http\Resources;

use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ringkasan finansial satu periode — angka dihitung di server supaya
 * aplikasi Flutter selalu konsisten dengan web (satu sumber kebenaran).
 */
class PeriodSummaryResource extends JsonResource
{
    public function __construct(Period $period, array $payload)
    {
        parent::__construct($period);
        $this->resource = $period;
        $this->payload = $payload;
    }

    public function toArray(Request $request): array
    {
        return $this->payload;
    }
}
