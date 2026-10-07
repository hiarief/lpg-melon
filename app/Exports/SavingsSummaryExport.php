<?php

namespace App\Exports;

use App\Models\Period;
use App\Models\Saving;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SavingsSummaryExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $year;

    public function __construct(int $year)
    {
        $this->year = $year;
    }

    public function collection()
    {
        $data = collect();
        $periods = Period::where('year', $this->year)->orderBy('month')->get();

        foreach ($periods as $period) {
            $savings = Saving::where('period_id', $period->id)->get();

            $totalIn = $savings->where('type', 'in')->sum('amount');
            $totalOut = $savings->where('type', 'out')->sum('amount');
            $openingBalance = $period->opening_surplus;
            $closingBalance = $openingBalance + $totalIn - $totalOut;

            $data->push([
                'periode'         => $period->label,
                'saldo_awal'      => $openingBalance,
                'total_masuk'     => $totalIn,
                'total_keluar'    => $totalOut,
                'saldo_akhir'     => $closingBalance,
                'jumlah_transaksi'=> $savings->count(),
            ]);

            // Rumus saldo akhir: saldo awal + masuk - keluar
            $rowNum = $data->count() + 1; // +1 karena header di row 1
            $data->transform(function ($item, $key) use ($rowNum, $data) {
                if ($key === $data->count() - 1) {
                    $item['saldo_akhir'] = "=B{$rowNum}+C{$rowNum}-D{$rowNum}";
                }
                return $item;
            });
        }

        // Total row
        $totalRow = $periods->count() + 2; // +1 untuk header, +1 untuk baris total
        $data->push([
            'periode'         => 'TOTAL',
            'saldo_awal'      => "=SUM(B2:B" . ($totalRow - 1) . ")",
            'total_masuk'     => "=SUM(C2:C" . ($totalRow - 1) . ")",
            'total_keluar'    => "=SUM(D2:D" . ($totalRow - 1) . ")",
            'saldo_akhir'     => "=SUM(E2:E" . ($totalRow - 1) . ")",
            'jumlah_transaksi'=> "=SUM(F2:F" . ($totalRow - 1) . ")",
        ]);

        return $data;
    }

    public function headings(): array
    {
        return [
            'Periode',
            'Saldo Awal (Rp)',
            'Total Masuk (Rp)',
            'Total Keluar (Rp)',
            'Saldo Akhir (Rp)',
            'Jumlah Transaksi',
        ];
    }

    public function title(): string
    {
        return 'Ringkasan ' . $this->year;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 18,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 16,
        ];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Data rows
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A2:F' . $lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Number format for currency columns
        $sheet->getStyle('B2:E' . $lastRow)->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        // Total row style
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE699']],
        ]);

        // Alternate row colors
        for ($row = 2; $row < $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
                ]);
            }
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setAutoFilter('A1:F' . $sheet->getHighestRow());
                $sheet->freezePane('A2');
            },
        ];
    }
}
