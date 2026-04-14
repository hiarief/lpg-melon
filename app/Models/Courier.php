<?php

namespace App\Models;

use App\Models\CourierDeposit;
use App\Models\Distribution;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    protected $fillable = ['name', 'wage_per_unit', 'is_active'];

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
    public function deposits(): HasMany
    {
        return $this->hasMany(CourierDeposit::class);
    }

    public function calculateWage(Period $period): int
    {
        $totalQty = $this->distributions()->where('period_id', $period->id)->sum('qty');
        return $totalQty * $this->wage_per_unit;
    }
}