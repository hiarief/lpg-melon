<?php

namespace App\Models;

use App\Models\AccountTransfer;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Saving extends Model
{
    protected $fillable = ['period_id', 'account_transfer_id', 'entry_date', 'type', 'amount', 'description'];

    protected $casts = ['entry_date' => 'date'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
    public function accountTransfer(): BelongsTo
    {
        return $this->belongsTo(AccountTransfer::class);
    }
}