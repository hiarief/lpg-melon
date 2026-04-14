<?php

namespace App\Models;

use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyExpense extends Model
{
    protected $fillable = ['period_id', 'expense_date', 'category', 'description', 'amount', 'notes'];

    protected $casts = ['expense_date' => 'date'];

    public static $categoryLabels = [
        'bensin' => 'Bensin',
        'rokok' => 'Rokok',
        'makan' => 'Makan',
        'sopir' => 'Sopir (Kontrak)',
        'oli_servis' => 'Oli / Servis',
        'ruko' => 'Sewa Ruko / Angga',
        'thr' => 'THR',
        'dll' => 'Lain-lain',
        'gaji_kurir' => 'Gaji Kurir',
        'kontrak_pangkalan' => 'Kontrak Pangkalan',
        'tabungan' => 'Tabungan',
        'rumah' => 'Rumah',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function categoryLabel(): string
    {
        return self::$categoryLabels[$this->category] ?? $this->category;
    }
}