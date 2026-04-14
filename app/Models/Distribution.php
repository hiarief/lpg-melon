<?php

namespace App\Models;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    protected $fillable = ['period_id', 'courier_id', 'customer_id', 'dist_date', 'qty', 'price_per_unit', 'payment_status', 'paid_amount', 'notes'];

    protected $casts = ['dist_date' => 'date'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function totalValue(): int
    {
        return $this->qty * $this->price_per_unit;
    }

    public function remainingAmount(): int
    {
        return max(0, $this->totalValue() - ($this->paid_amount ?? 0));
    }
}