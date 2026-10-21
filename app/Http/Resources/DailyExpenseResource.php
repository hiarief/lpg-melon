<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'period_id'    => $this->period_id,
            'expense_date' => $this->expense_date?->toDateString(),
            'category'     => $this->category,
            'category_label' => $this->categoryLabel(),
            'description'  => $this->description,
            'amount'       => (int) $this->amount,
            'notes'        => $this->notes,
        ];
    }
}
