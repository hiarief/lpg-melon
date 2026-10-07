<?php

namespace App\Exports;

use App\Models\Period;
use App\Models\DeliveryOrder;
use App\Models\Outlet;
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

class DeliveryOrderSummaryExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
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
        $outlets = Outlet::where('is_active', true)->get();

        foreach ($outlets as $outlet) {
            $row = [
                'pangkalan' => $outlet->name,
            ];

            $totalQty = 0;
            $totalValue = 0;
            $totalPaid = 0;
            $totalPiutang = 0;

            foreach ($periods as $period) {
                $dos = DeliveryOrder::where('period_id', $period->id)
                    ->where('outlet_id', $outlet->id)
                    ->get();

                $qty = $dos->sum('qty');
                $value = $dos->sum(fn ($d) => $d->qty * $d->price_per_unit);
                $paid = $dos->sum('paid_amount');
                $piutang = $value - $paid;

                $row[$period->label] = $qty;
                $totalQty += $qty;
                $totalValue += $value;
                $totalPaid += $paid;
                $totalPiutang += $piutang;
            }

            $row['total_qty'] = $totalQty;
            $row['total_value'] = $totalValue;
            $row['total_paid'] = $totalPaid;
            $row['total_piutang'] = $totalPiutang;

            $data->push($row);
        }

        // Total row dengan nilai langsung (bukan rumus, untuk menghindari masalah)
        $totalRow = ['TOTAL'];
        foreach ($periods as $period) {
            $periodDos = DeliveryOrder::where('period_id', $period->id)->get();
            $totalRow[] = $periodDos->sum('qty');
        }
        $totalRow[] = $data->sum('total_qty');
        $totalRow[] = $data->sum('total_value');
        $totalRow[] = $data->sum('total_paid');
        $totalRow[] = $data->sum('total_piutang');
        $data->push($totalRow);

        return $data;
    }

    public function headings(): array
    {
        $headings = ['Pangkalan'];
        $periods = Period::where('year', $this->year)->orderBy('month')->get();
        foreach ($periods as $period) {
            $headings[] = $period->label;
        }
        $headings[] = 'Total Qty';
        $headings[] = 'Total Nilai (Rp)';
        $headings[] = 'Total Terbayar (Rp)';
        $headings[] = 'Total Piutang (Rp)';
        return $headings;
    }

    public function title(): string
    {
        return 'Ringkasan ' . $this->year;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 12,
            'C' => 12,
            'D' => 12,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 12,
            'J' => 12,
            'K' => 12,
            'L' => 12,
            'M' => 12,
            'N' => 14,
            'O' => 16,
            'P' => 16,
            'Q' => 16,
        ];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $sheet->getStyle('A1:Q1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A2:Q' . $lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('N2:Q' . $lastRow)->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        // Total row style
        $sheet->getStyle('A' . $lastRow . ':Q' . $lastRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE699']],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('B2');
            },
        ];
    }
}
