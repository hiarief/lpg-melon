<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\OutletContractPayment;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = ['name', 'contract_type', 'contract_rate', 'is_active'];

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
    public function contractPayments(): HasMany
    {
        return $this->hasMany(OutletContractPayment::class);
    }

    /** Hitung nilai kontrak bulan ini */
    public function calculateContractAmount(Period $period): int
    {
        if ($this->contract_type === 'flat_monthly') {
            return $this->contract_rate;
        }
        if ($this->contract_type === 'per_do') {
            $total = $this->deliveryOrders()->where('period_id', $period->id)->sum('qty');
            return $total * $this->contract_rate;
        }
        return 0;
    }
}