<?php

namespace App\Exports;

use App\Models\Period;
use App\Models\DeliveryOrder;
use App\Models\Outlet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class DeliveryOrderPeriodExport implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $year;
    protected $periodId;

    public function __construct(int $year, ?int $periodId = null)
    {
        $this->year = $year;
        $this->periodId = $periodId;
    }

    public function array(): array
    {
        $data = [];

        $periods = $this->periodId
            ? Period::where('id', $this->periodId)->get()
            : Period::where('year', $this->year)->orderBy('month')->get();

        foreach ($periods as $period) {
            $outlets = Outlet::where('is_active', true)->get();
            $dos = DeliveryOrder::with(['outlet', 'transfers'])
                ->where('period_id', $period->id)
                ->orderBy('do_date')
                ->get();

            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);
            $matrixCols = $daysInMonth + 2; // Pangkalan + days + Total
            $detailStartCol = $matrixCols + 2; // 2 kolom spacer
            $totalCols = $detailStartCol + 8; // detail: 8 kolom

            // ═══ HEADER ═══
            $header = array_fill(0, $totalCols, '');
            $header[0] = 'Pangkalan';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $header[$d] = $d;
            }
            $header[$daysInMonth + 1] = 'Total';
            // Detail header
            $detailHeaders = ['Tanggal DO', 'Pangkalan', 'Qty', 'Nilai DO', 'Tanggal Transfer', 'Jumlah Transfer', 'Status', 'Surplus'];
            for ($i = 0; $i < count($detailHeaders); $i++) {
                $header[$detailStartCol + $i] = $detailHeaders[$i];
            }
            $data[] = $header;

            // ═══ DATA PANGKALAN ═══
            foreach ($outlets as $outlet) {
                $outletDOs = $dos->where('outlet_id', $outlet->id);
                $row = array_fill(0, $totalCols, '');
                $row[0] = $outlet->name;
                $totalQty = 0;
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $period->year, $period->month, $day);
                    $dayDOs = $outletDOs->filter(fn ($d) => $d->do_date->format('Y-m-d') === $dateStr);
                    $qty = $dayDOs->sum('qty');
                    $row[$day] = $qty > 0 ? $qty : '';
                    $totalQty += $qty;
                }
                $row[$daysInMonth + 1] = $totalQty;
                $data[] = $row;
            }

            // ═══ TOTAL ROW ═══
            $totalRow = array_fill(0, $totalCols, '');
            $totalRow[0] = 'TOTAL';
            $grandTotal = 0;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $period->year, $period->month, $day);
                $dayTotal = $dos->filter(fn ($d) => $d->do_date->format('Y-m-d') === $dateStr)->sum('qty');
                $totalRow[$day] = $dayTotal > 0 ? $dayTotal : '';
                $grandTotal += $dayTotal;
            }
            $totalRow[$daysInMonth + 1] = $grandTotal;
            $data[] = $totalRow;

            // ═══ DETAIL TRANSAKSI ═══
            $detailRowIdx = 1; // mulai dari baris setelah header
            foreach ($dos->groupBy(fn ($d) => $d->do_date->format('Y-m-d')) as $dateStr => $dayDOs) {
                foreach ($dayDOs as $do) {
                    $transfers = $do->transfers;
                    $transferDate = $transfers->isNotEmpty() ? $transfers->first()->transfer_date->format('d/m/Y') : '—';
                    $transferAmount = $transfers->sum('amount');
                    $status = $do->payment_status === 'paid' ? 'Lunas' : 'Belum';
                    $surplus = $transfers->sum('surplus');

                    $detailRow = array_fill(0, $totalCols, '');
                    $detailRow[$detailStartCol] = $do->do_date->format('d/m/Y');
                    $detailRow[$detailStartCol + 1] = $do->outlet->name ?? '-';
                    $detailRow[$detailStartCol + 2] = $do->qty;
                    $detailRow[$detailStartCol + 3] = $do->qty * $do->price_per_unit;
                    $detailRow[$detailStartCol + 4] = $transferDate;
                    $detailRow[$detailStartCol + 5] = $transferAmount;
                    $detailRow[$detailStartCol + 6] = $status;
                    $detailRow[$detailStartCol + 7] = $surplus;

                    if (isset($data[$detailRowIdx])) {
                        for ($i = $detailStartCol; $i < $totalCols; $i++) {
                            $data[$detailRowIdx][$i] = $detailRow[$i];
                        }
                    } else {
                        $data[] = $detailRow;
                    }
                    $detailRowIdx++;
                }
            }
        }

        return $data;
    }

    public function title(): string
    {
        if ($this->periodId) {
            $period = Period::find($this->periodId);
            return $period ? $period->label : 'DO';
        }
        return 'DO ' . $this->year;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 16];
        for ($i = 2; $i <= 35; $i++) {
            $widths[Coordinate::stringFromColumnIndex($i)] = 6;
        }
        $widths[Coordinate::stringFromColumnIndex(36)] = 10; // Total
        $widths[Coordinate::stringFromColumnIndex(37)] = 3;  // spacer
        $widths[Coordinate::stringFromColumnIndex(38)] = 3;  // spacer
        $widths[Coordinate::stringFromColumnIndex(39)] = 14; // Tanggal DO
        $widths[Coordinate::stringFromColumnIndex(40)] = 16; // Pangkalan
        $widths[Coordinate::stringFromColumnIndex(41)] = 8;  // Qty
        $widths[Coordinate::stringFromColumnIndex(42)] = 14; // Nilai DO
        $widths[Coordinate::stringFromColumnIndex(43)] = 16; // Tanggal Transfer
        $widths[Coordinate::stringFromColumnIndex(44)] = 16; // Jumlah Transfer
        $widths[Coordinate::stringFromColumnIndex(45)] = 10; // Status
        $widths[Coordinate::stringFromColumnIndex(46)] = 14; // Surplus
        return $widths;
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A2:{$highestCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("E2:{$highestCol}{$lastRow}")->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn();

                // Rumus Total per baris (kolom Total = SUM baris)
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $this->periodId ? Period::find($this->periodId)->month : 1, $this->year);
                $totalCol = $daysInMonth + 2;
                $totalColLetter = Coordinate::stringFromColumnIndex($totalCol);
                $firstDataCol = Coordinate::stringFromColumnIndex(2);
                $lastDataCol = Coordinate::stringFromColumnIndex($daysInMonth + 1);

                for ($row = 2; $row <= $highestRow; $row++) {
                    // Cek apakah baris ini baris data pangkalan (bukan total)
                    $cellA = $sheet->getCell("A{$row}")->getValue();
                    if ($cellA && $cellA !== 'TOTAL') {
                        $sheet->setCellValue(
                            "{$totalColLetter}{$row}",
                            "=SUM({$firstDataCol}{$row}:{$lastDataCol}{$row})"
                        );
                    }
                }

                // Rumus Total per kolom (baris TOTAL)
                $totalRow = null;
                for ($row = 2; $row <= $highestRow; $row++) {
                    if ($sheet->getCell("A{$row}")->getValue() === 'TOTAL') {
                        $totalRow = $row;
                        break;
                    }
                }

                if ($totalRow) {
                    for ($col = 2; $col <= $daysInMonth + 1; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $sheet->setCellValue(
                            "{$colLetter}{$totalRow}",
                            "=SUM({$colLetter}2:{$colLetter}" . ($totalRow - 1) . ")"
                        );
                    }
                    // Total grand total
                    $sheet->setCellValue(
                        "{$totalColLetter}{$totalRow}",
                        "=SUM({$totalColLetter}2:{$totalColLetter}" . ($totalRow - 1) . ")"
                    );
                }

                // Rumus total di detail section
                $detailStartCol = $totalCol + 3;
                $detailTotalRow = $highestRow + 1;
                $sheet->setCellValue("A{$detailTotalRow}", 'TOTAL');
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($detailStartCol + 2) . "{$detailTotalRow}",
                    "=SUM(" . Coordinate::stringFromColumnIndex($detailStartCol + 2) . "2:" . Coordinate::stringFromColumnIndex($detailStartCol + 2) . "{$highestRow})"
                );
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($detailStartCol + 3) . "{$detailTotalRow}",
                    "=SUM(" . Coordinate::stringFromColumnIndex($detailStartCol + 3) . "2:" . Coordinate::stringFromColumnIndex($detailStartCol + 3) . "{$highestRow})"
                );
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($detailStartCol + 5) . "{$detailTotalRow}",
                    "=SUM(" . Coordinate::stringFromColumnIndex($detailStartCol + 5) . "2:" . Coordinate::stringFromColumnIndex($detailStartCol + 5) . "{$highestRow})"
                );
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($detailStartCol + 7) . "{$detailTotalRow}",
                    "=SUM(" . Coordinate::stringFromColumnIndex($detailStartCol + 7) . "2:" . Coordinate::stringFromColumnIndex($detailStartCol + 7) . "{$highestRow})"
                );

                $sheet->getStyle("A{$detailTotalRow}:" . $highestCol . "{$detailTotalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE699']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->freezePane('B2');
            },
        ];
    }
}
