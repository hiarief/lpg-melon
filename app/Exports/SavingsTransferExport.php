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

class SavingsTransferExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $year;
    protected $periodId;

    public function __construct(int $year, ?int $periodId = null)
    {
        $this->year = $year;
        $this->periodId = $periodId;
    }

    public function collection()
    {
        $data = collect();

        $periods = $this->periodId
            ? Period::where('id', $this->periodId)->get()
            : Period::where('year', $this->year)->orderBy('month')->get();

        foreach ($periods as $period) {
            $savings = Saving::with(['accountTransfer', 'accountTransfer.deliveryOrders'])
                ->where('period_id', $period->id)
                ->get()
                ->sortBy(function ($s) {
                    return $s->accountTransfer
                        ? $s->accountTransfer->transfer_date
                        : $s->entry_date;
                });

            foreach ($savings as $s) {
                $tf = $s->accountTransfer;
                $doList = $tf ? $tf->deliveryOrders : collect();
                $earliestDo = $doList->sortBy('do_date')->first();

                $data->push([
                    'periode'         => $period->label,
                    'tanggal_do'      => $earliestDo ? $earliestDo->do_date->format('d/m/Y') : '—',
                    'tanggal_transfer'=> $tf ? $tf->transfer_date->format('d/m/Y') : '—',
                    'tanggal_entry'   => $s->entry_date->format('d/m/Y'),
                    'jenis'           => $s->type === 'in' ? 'Masuk' : 'Keluar',
                    'masuk'           => $s->type === 'in' ? $s->amount : 0,
                    'keluar'          => $s->type === 'out' ? $s->amount : 0,
                    'saldo'           => 0, // placeholder, akan diisi rumus
                ]);
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Periode',
            'Tanggal DO',
            'Tanggal Transfer',
            'Tanggal Entry',
            'Jenis',
            'Masuk (Rp)',
            'Keluar (Rp)',
            'Saldo (Rp)',
        ];
    }

    public function title(): string
    {
        if ($this->periodId) {
            $period = Period::find($this->periodId);
            return $period ? $period->label : 'Tabungan';
        }
        return 'Tabungan ' . $this->year;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 14,
            'C' => 16,
            'D' => 14,
            'E' => 8,
            'F' => 16,
            'G' => 16,
            'H' => 16,
        ];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A2:H' . $lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('F2:H' . $lastRow)->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        for ($row = 2; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
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

                // Cari periodeId untuk ambil saldo awal
                $periodId = $this->periodId;
                if ($periodId) {
                    $period = Period::find($periodId);
                    $openingBalance = $period ? $period->opening_surplus : 0;
                } else {
                    $openingBalance = 0;
                }

                // Tambah baris saldo awal di baris 2 (setelah header)
                $sheet->insertNewRowBefore(2, 1);
                $sheet->setCellValue('A2', $period ? $period->label : '');
                $sheet->setCellValue('B2', '—');
                $sheet->setCellValue('C2', '—');
                $sheet->setCellValue('D2', 'Awal');
                $sheet->setCellValue('E2', '—');
                $sheet->setCellValue('F2', 0);
                $sheet->setCellValue('G2', 0);
                $sheet->setCellValue('H2', $openingBalance);

                // Style baris saldo awal
                $sheet->getStyle('A2:H2')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Ambil highestRow SETELAH insert baris saldo awal
                $highestRow = $sheet->getHighestRow();

                // Rumus Saldo (running balance) untuk baris 3+
                for ($row = 3; $row <= $highestRow; $row++) {
                    $prevSaldo = $row - 1;
                    $sheet->setCellValue(
                        "H{$row}",
                        "=H{$prevSaldo}+F{$row}-G{$row}"
                    );
                }

                // Tambah baris TOTAL di bawah
                $totalRow = $highestRow + 1;
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->setCellValue("F{$totalRow}", "=SUM(F3:F{$highestRow})");
                $sheet->setCellValue("G{$totalRow}", "=SUM(G3:G{$highestRow})");
                $sheet->setCellValue("H{$totalRow}", "=H{$highestRow}");

                // Force recalculate highest row
                $sheet->getHighestRow();

                // Style baris total
                $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE699']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Number format untuk baris total
                $sheet->getStyle("F{$totalRow}:H{$totalRow}")->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                // Auto-filter (exclude baris saldo awal dan total)
                $sheet->setAutoFilter("A1:H{$highestRow}");

                // Freeze top row
                $sheet->freezePane('A2');
            },
        ];
    }
}
