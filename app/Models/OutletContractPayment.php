<?php

namespace App\Models;

use App\Models\Outlet;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletContractPayment extends Model
{
    protected $fillable = ['period_id', 'outlet_id', 'calculated_amount', 'paid_amount', 'paid_date', 'status', 'notes'];

    protected $casts = ['paid_date' => 'date'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}