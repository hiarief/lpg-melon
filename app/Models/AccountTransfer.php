<?php

namespace App\Models;

use App\Models\DeliveryOrder;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccountTransfer extends Model
{
    protected $fillable = ['period_id', 'transfer_date', 'amount', 'do_equivalent_qty', 'surplus', 'notes'];

    protected $casts = ['transfer_date' => 'date'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function deliveryOrders(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryOrder::class, 'account_transfer_do', 'account_transfer_id', 'delivery_order_id')->withPivot('amount_allocated');
    }
}