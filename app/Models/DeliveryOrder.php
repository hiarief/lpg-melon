<?php

namespace App\Models;

use App\Models\AccountTransfer;
use App\Models\Outlet;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeliveryOrder extends Model
{
    protected $casts = [
        'do_date' => 'date', // 🔥 INI KUNCINYA
    ];
    protected $fillable = ['period_id', 'outlet_id', 'do_date', 'qty', 'price_per_unit', 'payment_status', 'paid_amount', 'notes'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
    public function transfers(): BelongsToMany
    {
        return $this->belongsToMany(AccountTransfer::class, 'account_transfer_do', 'delivery_order_id', 'account_transfer_id')->withPivot('amount_allocated');
    }

    public function totalValue(): int
    {
        return $this->qty * $this->price_per_unit;
    }

    public function remainingAmount(): int
    {
        return max(0, $this->totalValue() - $this->paid_amount);
    }

    /** Recalculate paid_amount from pivot table & update status */
    public function recalcPayment(): void
    {
        $paid = $this->transfers()->sum('account_transfer_do.amount_allocated');
        $this->paid_amount = $paid;
        $total = $this->totalValue();
        $this->payment_status = $paid <= 0 ? 'unpaid' : ($paid >= $total ? 'paid' : 'partial');
        $this->save();
    }
}