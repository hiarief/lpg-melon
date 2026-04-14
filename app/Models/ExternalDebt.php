<?php

namespace App\Models;

use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalDebt extends Model
{
    protected $fillable = ['period_id', 'entry_date', 'type', 'source_name', 'amount', 'description'];

    protected $casts = ['entry_date' => 'date'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}