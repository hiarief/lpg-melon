<?php

namespace App\Exports;

use App\Models\Period;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DeliveryOrderYearExport implements WithMultipleSheets
{
    protected $year;

    public function __construct(int $year)
    {
        $this->year = $year;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet ringkasan
        $sheets[] = new DeliveryOrderSummaryExport($this->year);

        // Sheet per periode (bulan)
        $periods = Period::where('year', $this->year)->orderBy('month')->get();
        foreach ($periods as $period) {
            $sheets[] = new DeliveryOrderPeriodExport($this->year, $period->id);
        }

        return $sheets;
    }
}
