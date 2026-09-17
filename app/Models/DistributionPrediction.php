<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;

// app/Models/DistributionPrediction.php
class DistributionPrediction extends Model
{
    protected $fillable = [
        'customer_id', 'source_period_id', 'target_period_id',
        'predicted_day', 'predicted_date', 'predicted_qty',
        'avg_interval', 'confidence', 'generated_at',
    ];

    protected $casts = [
        'predicted_date' => 'date',
        'generated_at'   => 'datetime',
    ];

    public function customer()     { return $this->belongsTo(Customer::class); }
    public function sourcePeriod() { return $this->belongsTo(Period::class, 'source_period_id'); }
    public function targetPeriod() { return $this->belongsTo(Period::class, 'target_period_id'); }
}
