<?php

namespace App\Exports;

use App\Models\Period;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SavingsYearExport implements WithMultipleSheets
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
        $sheets[] = new SavingsSummaryExport($this->year);

        // Sheet per periode (bulan)
        $periods = Period::where('year', $this->year)->orderBy('month')->get();
        foreach ($periods as $period) {
            $sheets[] = new SavingsTransferExport($this->year, $period->id);
        }

        return $sheets;
    }
}
