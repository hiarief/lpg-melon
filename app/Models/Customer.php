<?php

namespace App\Models;

use App\Models\Distribution;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'type', 'outlet_id', 'is_active'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function isContract(): bool
    {
        return $this->type === 'contract';
    }
}