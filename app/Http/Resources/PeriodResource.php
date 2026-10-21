<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'year'       => (int) $this->year,
            'month'      => (int) $this->month,
            'label'      => $this->label,
            'status'     => $this->status,
            'opening_stock'         => (int) $this->opening_stock,
            'opening_cash'          => (int) $this->opening_cash,
            'opening_penampung'     => (int) $this->opening_penampung,
            'opening_external_debt' => (int) $this->opening_external_debt,
            'opening_do_unpaid_qty' => (int) $this->opening_do_unpaid_qty,
            'opening_surplus'       => (int) ($this->opening_surplus ?? 0),
            // Derived: saldo tabungan berjalan (tabungan di rek utama)
            'savings_balance'       => (int) $this->totalSavingsBalance(),
            'total_do_qty'          => (int) $this->totalDoQty(),
            'total_dist_qty'        => (int) $this->totalDistQty(),
            'stock_remaining'       => (int) ($this->opening_stock + $this->totalDoQty() - $this->totalDistQty()),
        ];
    }
}
