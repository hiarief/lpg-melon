<?php

namespace App\Models;

use App\Models\Courier;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierDeposit extends Model
{
    protected $fillable = ['period_id', 'courier_id', 'deposit_date', 'amount', 'admin_fee', 'reference_no', 'notes'];

    protected $casts = ['deposit_date' => 'date'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }
}