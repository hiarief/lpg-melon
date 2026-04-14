<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\DailyExpense;
use App\Models\CourierDeposit;
use App\Models\AccountTransfer;
use App\Models\ExternalDebt;
use App\Models\OutletContractPayment;
use App\Models\Outlet;
use App\Models\Courier;
use App\Models\Saving;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Font, Alignment, Border, NumberFormat};
use PhpOffice\PhpSpreadsheet\Style\Border as B;

class ExportController extends Controller
{
    // ─── Color palette ────────────────────────────────────────────────
    const C_HEADER_BG  = 'FFD35400'; // orange tua
    const C_HEADER_FG  = 'FFFFFFFF';
    const C_SUB_BG     = 'FFFFF3E0'; // orange muda
    const C_TITLE_BG   = 'FFFFE0B2';
    const C_GREEN_BG   = 'FFE8F5E9';
    const C_GREEN_FG   = 'FF2E7D32';
    const C_RED_FG     = 'FFC62828';
    const C_BLUE_BG    = 'FFE3F2FD';
    const C_BLUE_FG    = 'FF1565C0';
    const C_GRAY_BG    = 'FFF5F5F5';
    const C_GRAY_FG    = 'FF616161';
    const C_YELLOW_BG  = 'FFFFF9C4';
    const C_CARRY_BG   = 'FFFFF8E1'; // amber muda untuk carry-over

    public function export(Request $request)
    {
        // ── Fix #3: Naikkan batas memory & waktu eksekusi ─────────────
        ini_set('memory_limit', '256M');
        set_time_limit(120);

        // ── Fix #1: Bersihkan buffer output sebelum proses dimulai ────
        // Hindari stray output (BOM, spasi, debug bar, dll) yang
        // merusak header HTTP sebelum kita sempat mengirimnya.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $periodId = $request->period_id ?? Period::current()?->id;
        $period   = Period::findOrFail($periodId);

        // ── Bangun spreadsheet (semua logika sama persis) ─────────────
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Rekap Gas LPG 3KG — {$period->label}")
            ->setCreator('Sistem Rekap LPG');

        $spreadsheet->removeSheetByIndex(0); // hapus sheet default kosong

        $this->sheetRingkasan($spreadsheet, $period);
        $this->sheetDoAgen($spreadsheet, $period);
        $this->sheetDistribusi($spreadsheet, $period);
        $this->sheetCashflow($spreadsheet, $period);
        $this->sheetTransfer($spreadsheet, $period);
        $this->sheetTabungan($spreadsheet, $period);
        $this->sheetPiutangExternal($spreadsheet, $period);
        $this->sheetKontrakPangkalan($spreadsheet, $period);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Rekap_LPG_' . str_replace(' ', '_', $period->label) . '_' . date('Ymd') . '.xlsx';

        // ── Fix #2: Gunakan streamDownload Laravel (bukan header/exit) ─
        // Cara ini aman terhadap middleware Laravel (session, cookies,
        // debug bar, dll) yang bisa mengacaukan raw header() + exit.
        return response()->streamDownload(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control'       => 'max-age=0',
                'Pragma'              => 'public',
                'Expires'             => '0',
            ]
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function newSheet(Spreadsheet $wb, string $title): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $ws = $wb->createSheet();
        $ws->setTitle($title);
        $ws->getDefaultColumnDimension()->setWidth(14);
        $ws->getDefaultRowDimension()->setRowHeight(16);
        return $ws;
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $range,
                                  string $bg = self::C_HEADER_BG, string $fg = self::C_HEADER_FG): void
    {
        $ws->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => ltrim($fg,'F')], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => ltrim($bg,'F')]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => B::BORDER_THIN, 'color' => ['rgb' => 'FFCCCCCC']]],
        ]);
    }

    private function styleTitle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $cell, string $text, int $size = 13): void
    {
        $ws->setCellValue($cell, $text);
        $ws->getStyle($cell)->applyFromArray([
            'font'      => ['bold' => true, 'size' => $size, 'color' => ['rgb' => ltrim(self::C_HEADER_BG,'F')]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
    }

    private function rp(int $val): string
    {
        return 'Rp ' . number_format($val, 0, ',', '.');
    }

    private function setBorders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $range): void
    {
        $ws->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders'  => ['borderStyle' => B::BORDER_THIN,   'color' => ['rgb' => 'FFDDDDDD']],
                'outline'     => ['borderStyle' => B::BORDER_MEDIUM,  'color' => ['rgb' => 'FFAAAAAA']],
            ],
        ]);
    }

    private function rowBg(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row, int $colFrom, int $colTo, string $hex): void
    {
        $from = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colFrom) . $row;
        $to   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colTo) . $row;
        $ws->getStyle("$from:$to")->getFill()->setFillType(Fill::FILL_SOLID)
           ->getStartColor()->setARGB('FF' . ltrim($hex,'F'));
    }

    private function money(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $range): void
    {
        $ws->getStyle($range)->getNumberFormat()->setFormatCode('#,##0;(#,##0);-');
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. SHEET RINGKASAN
    // ═══════════════════════════════════════════════════════════════════
    private function sheetRingkasan(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '1. Ringkasan');
        $ws->getColumnDimension('A')->setWidth(32);
        $ws->getColumnDimension('B')->setWidth(22);
        $ws->getColumnDimension('C')->setWidth(40);

        $this->styleTitle($ws, 'A1', "RINGKASAN — {$period->label}", 14);
        $ws->mergeCells('A1:C1');

        // Saldo Awal
        $ws->setCellValue('A3', '⚙ PARAMETER AWAL PERIODE');
        $ws->getStyle('A3')->getFont()->setBold(true)->setSize(11);
        $ws->getStyle('A3')->getFont()->getColor()->setARGB('FFD35400');

        $params = [
            ['Stok Tabung Awal', $period->opening_stock . ' tabung'],
            ['Saldo Kas Fisik Awal', $this->rp($period->opening_cash)],
            ['Saldo Rek Penampung Awal', $this->rp($period->opening_penampung)],
            ['Piutang External Awal', $this->rp($period->opening_external_debt)],
            ['Tabungan / Surplus Awal', $this->rp($period->opening_surplus ?? 0)],
            ['DO Belum Lunas Bawaan', $period->opening_do_unpaid_qty . ' tabung'],
        ];
        $r = 4;
        foreach ($params as $p) {
            $ws->setCellValue("A$r", $p[0]);
            $ws->setCellValue("B$r", $p[1]);
            $ws->getStyle("B$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $this->rowBg($ws, $r, 1, 3, 'FFF3E0');
            $r++;
        }

        // Stok
        $r++;
        $ws->setCellValue("A$r", '📦 STOK');
        $ws->getStyle("A$r")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFD35400');
        $r++;

        $outlets = Outlet::where('is_active', true)->get();
        $doByOutlet = DeliveryOrder::where('period_id', $period->id)
            ->where(fn($q) => $q->whereNull('notes')->orWhere('notes','not like','%Carry-over%'))
            ->selectRaw('outlet_id, SUM(qty) as total')->groupBy('outlet_id')
            ->pluck('total','outlet_id');
        $carryoverByOutlet = DeliveryOrder::where('period_id', $period->id)
            ->where('notes','like','%Carry-over%')
            ->selectRaw('outlet_id, SUM(qty) as total')->groupBy('outlet_id')
            ->pluck('total','outlet_id');

        $totalDo    = $doByOutlet->sum();
        $totalCarry = $carryoverByOutlet->sum();
        $totalDist  = Distribution::where('period_id', $period->id)->sum('qty');
        $stockSisa  = $period->opening_stock + $totalDo - $totalDist;

        $stockRows = [
            ['DO Masuk Bulan Ini (baru)', $totalDo . ' tabung'],
            ['DO Carry-Over (piutang lalu)', $totalCarry . ' tabung (tidak dihitung stok)'],
            ['Total Distribusi', $totalDist . ' tabung'],
            ['Stok Sisa Akhir', $stockSisa . ' tabung'],
        ];
        foreach ($stockRows as $sr) {
            $ws->setCellValue("A$r", $sr[0]);
            $ws->setCellValue("B$r", $sr[1]);
            $ws->getStyle("B$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $r++;
        }

        // Keuangan
        $r++;
        $ws->setCellValue("A$r", '💰 KEUANGAN');
        $ws->getStyle("A$r")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFD35400');
        $r++;

        $salesRows = Distribution::where('period_id', $period->id)->get(['qty','price_per_unit']);
        $totalSales  = $salesRows->sum(fn($d) => $d->qty * $d->price_per_unit);
        $totalModal  = $totalDist * 16000;
        $grossMargin = $totalSales - $totalModal;
        $totalExpense = DailyExpense::where('period_id', $period->id)->sum('amount');
        $totalDeposits = CourierDeposit::where('period_id', $period->id)->sum('net_amount');
        $totalTransferred = AccountTransfer::where('period_id', $period->id)->sum('amount');
        $penampung = $period->opening_penampung + $totalDeposits - $totalTransferred;
        $savingsIn  = Saving::where('period_id', $period->id)->where('type','in')->sum('amount');
        $savingsOut = Saving::where('period_id', $period->id)->where('type','out')->sum('amount');
        $savingsBal = ($period->opening_surplus ?? 0) + $savingsIn - $savingsOut;

        $finRows = [
            ['Total Penjualan Bruto',          $this->rp($totalSales),     'green'],
            ['Modal Tabung (×Rp16.000)',        '(' . $this->rp($totalModal) . ')', 'red'],
            ['Gross Margin',                    $this->rp($grossMargin),    'green'],
            ['Total Pengeluaran Operasional',   '(' . $this->rp($totalExpense) . ')', 'red'],
            ['Net Cashflow',                    $this->rp($totalSales - $totalExpense - $totalDeposits), 'blue'],
            ['Saldo Rek Penampung Saat Ini',    $this->rp($penampung),      'blue'],
            ['Saldo Tabungan (Surplus)',         $this->rp($savingsBal),    'yellow'],
        ];
        foreach ($finRows as $fr) {
            $ws->setCellValue("A$r", $fr[0]);
            $ws->setCellValue("B$r", $fr[1]);
            $ws->getStyle("B$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $fgColor = match($fr[2]) {
                'green'  => 'FF2E7D32',
                'red'    => 'FFC62828',
                'blue'   => 'FF1565C0',
                'yellow' => 'FF827717',
                default  => 'FF212121',
            };
            $ws->getStyle("B$r")->getFont()->getColor()->setARGB($fgColor);
            $r++;
        }

        $this->setBorders($ws, "A3:C" . ($r - 1));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. SHEET DO AGEN
    // ═══════════════════════════════════════════════════════════════════
    private function sheetDoAgen(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '2. DO Agen');
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(18);
        $ws->getColumnDimension('C')->setWidth(13);
        $ws->getColumnDimension('D')->setWidth(8);
        $ws->getColumnDimension('E')->setWidth(14);
        $ws->getColumnDimension('F')->setWidth(14);
        $ws->getColumnDimension('G')->setWidth(14);
        $ws->getColumnDimension('H')->setWidth(10);
        $ws->getColumnDimension('I')->setWidth(35);

        $this->styleTitle($ws, 'A1', "DO AGEN — {$period->label}");
        $ws->mergeCells('A1:I1');

        // Header
        $headers = ['No','Pangkalan','Tanggal DO','Qty','Harga/Tab','Nilai DO','Terbayar','Status','Catatan'];
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$col}2", $h);
        }
        $this->styleHeader($ws, 'A2:I2');

        // DO biasa
        $dos = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->where(fn($q) => $q->whereNull('notes')->orWhere('notes','not like','%Carry-over%'))
            ->orderBy('do_date')->get();

        $r = 3;
        foreach ($dos as $i => $do) {
            $ws->setCellValue("A$r", $i + 1);
            $ws->setCellValue("B$r", $do->outlet->name);
            $ws->setCellValue("C$r", $do->do_date->format('d/m/Y'));
            $ws->setCellValue("D$r", $do->qty);
            $ws->setCellValue("E$r", $do->price_per_unit);
            $ws->setCellValue("F$r", $do->qty * $do->price_per_unit);
            $ws->setCellValue("G$r", $do->paid_amount);
            $ws->setCellValue("H$r", match($do->payment_status) {
                'paid'    => 'Lunas',
                'partial' => 'Sebagian',
                default   => 'Belum',
            });
            $ws->setCellValue("I$r", $do->notes);
            $this->money($ws, "E$r:G$r");

            $bg = match($do->payment_status) {
                'paid'    => 'E8F5E9',
                'partial' => 'FFF9C4',
                default   => 'FFEBEE',
            };
            $this->rowBg($ws, $r, 1, 9, $bg);
            $r++;
        }

        // Total row
        if ($dos->count() > 0) {
            $ws->setCellValue("A$r", 'TOTAL');
            $ws->setCellValue("D$r", "=SUM(D3:D" . ($r-1) . ")");
            $ws->setCellValue("F$r", "=SUM(F3:F" . ($r-1) . ")");
            $ws->setCellValue("G$r", "=SUM(G3:G" . ($r-1) . ")");
            $this->styleHeader($ws, "A$r:I$r", 'FFD35400');
            $this->money($ws, "F$r:G$r");
            $r++;
        }

        // Carry-over section
        $carryovers = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->where('notes','like','%Carry-over%')
            ->orderBy('do_date')->get();

        if ($carryovers->count() > 0) {
            $r++;
            $ws->setCellValue("A$r", '↩ CARRY-OVER (Piutang dari Bulan Lalu — BUKAN stok masuk baru)');
            $ws->mergeCells("A$r:I$r");
            $ws->getStyle("A$r")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFE65100']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFFF8E1']],
            ]);
            $r++;

            foreach ($carryovers as $i => $do) {
                $ws->setCellValue("A$r", $i + 1);
                $ws->setCellValue("B$r", $do->outlet->name);
                $ws->setCellValue("C$r", $do->do_date->format('d/m/Y'));
                $ws->setCellValue("D$r", $do->qty);
                $ws->setCellValue("E$r", $do->price_per_unit);
                $ws->setCellValue("F$r", $do->qty * $do->price_per_unit);
                $ws->setCellValue("G$r", $do->paid_amount);
                $ws->setCellValue("H$r", match($do->payment_status) {
                    'paid'    => 'Lunas',
                    'partial' => 'Sebagian',
                    default   => 'Belum',
                });
                $ws->setCellValue("I$r", 'CARRY-OVER: ' . ($do->notes ?? ''));
                $this->money($ws, "E$r:G$r");
                $this->rowBg($ws, $r, 1, 9, 'FFF3E0');
                $r++;
            }
        }

        $this->setBorders($ws, "A2:I" . ($r - 1));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. SHEET DISTRIBUSI HARIAN
    // ═══════════════════════════════════════════════════════════════════
    private function sheetDistribusi(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '3. Distribusi Harian');
        $ws->getColumnDimension('A')->setWidth(6);
        foreach (['B','C','D','E','F','G','H','I','J'] as $col) {
            $ws->getColumnDimension($col)->setWidth(14);
        }

        $this->styleTitle($ws, 'A1', "DISTRIBUSI HARIAN — {$period->label}");
        $ws->mergeCells('A1:J1');

        $headers = ['No','Customer','Tipe','Tanggal','Kurir','Qty','Harga/Tab','Total','Bayar','Status'];
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$col}2", $h);
        }
        $this->styleHeader($ws, 'A2:J2');

        $distributions = Distribution::with(['customer','courier'])
            ->where('period_id', $period->id)
            ->orderBy('dist_date')->orderBy('customer_id')->get();

        $r = 3;
        foreach ($distributions as $i => $d) {
            $ws->setCellValue("A$r", $i + 1);
            $ws->setCellValue("B$r", $d->customer->name);
            $ws->setCellValue("C$r", $d->customer->type === 'contract' ? '★ Kontrak' : 'Regular');
            $ws->setCellValue("D$r", $d->dist_date->format('d/m/Y'));
            $ws->setCellValue("E$r", $d->courier->name);
            $ws->setCellValue("F$r", $d->qty);
            $ws->setCellValue("G$r", $d->price_per_unit);
            $ws->setCellValue("H$r", $d->qty * $d->price_per_unit);
            $ws->setCellValue("I$r", $d->paid_amount);
            $ws->setCellValue("J$r", match($d->payment_status) {
                'paid'     => 'Lunas',
                'deferred' => 'Tunda',
                'partial'  => 'Sebagian',
                default    => '-',
            });
            $this->money($ws, "G$r:I$r");
            if ($d->customer->type === 'contract') {
                $this->rowBg($ws, $r, 1, 10, 'FFF8BBD9');
            }
            $r++;
        }

        // Total
        if ($distributions->count() > 0) {
            $ws->setCellValue("A$r", 'TOTAL');
            $ws->setCellValue("F$r", "=SUM(F3:F" . ($r-1) . ")");
            $ws->setCellValue("H$r", "=SUM(H3:H" . ($r-1) . ")");
            $ws->setCellValue("I$r", "=SUM(I3:I" . ($r-1) . ")");
            $this->styleHeader($ws, "A$r:J$r", 'FFD35400');
            $this->money($ws, "H$r:I$r");
        }

        $this->setBorders($ws, "A2:J$r");
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. SHEET CASHFLOW HARIAN
    // ═══════════════════════════════════════════════════════════════════
    private function sheetCashflow(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '4. Cashflow Harian');
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(22);
        $ws->getColumnDimension('C')->setWidth(13);
        $ws->getColumnDimension('D')->setWidth(14);
        $ws->getColumnDimension('E')->setWidth(35);

        $this->styleTitle($ws, 'A1', "CASHFLOW HARIAN — {$period->label}");
        $ws->mergeCells('A1:E1');

        $headers = ['No','Kategori','Tanggal','Nominal (Rp)','Keterangan'];
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$col}2", $h);
        }
        $this->styleHeader($ws, 'A2:E2');

        $expenses = DailyExpense::where('period_id', $period->id)
            ->orderBy('expense_date')->orderBy('category')->get();

        $r = 3;

        // Saldo Awal
        if ($period->opening_cash > 0) {
            $ws->setCellValue("A$r", 0);
            $ws->setCellValue("B$r", 'Saldo Awal (Cutoff Lalu)');
            $ws->setCellValue("C$r", '01/' . sprintf('%02d', $period->month) . '/' . $period->year);
            $ws->setCellValue("D$r", $period->opening_cash);
            $ws->setCellValue("E$r", 'Saldo kas dibawa dari periode sebelumnya');
            $this->money($ws, "D$r");
            $this->rowBg($ws, $r, 1, 5, 'E8F5E9');
            $ws->getStyle("D$r")->getFont()->getColor()->setARGB('FF2E7D32');
            $r++;
        }

        foreach ($expenses as $i => $e) {
            $ws->setCellValue("A$r", $i + 1);
            $ws->setCellValue("B$r", DailyExpense::$categoryLabels[$e->category] ?? $e->category);
            $ws->setCellValue("C$r", $e->expense_date->format('d/m/Y'));
            $ws->setCellValue("D$r", $e->amount);
            $ws->setCellValue("E$r", $e->description);
            $this->money($ws, "D$r");
            $r++;
        }

        // TF ke Penampung
        $deposits = CourierDeposit::with('courier')
            ->where('period_id', $period->id)
            ->orderBy('deposit_date')->get();

        foreach ($deposits as $dep) {
            $ws->setCellValue("A$r", '-');
            $ws->setCellValue("B$r", 'TF ke Penampung — ' . $dep->courier->name);
            $ws->setCellValue("C$r", $dep->deposit_date->format('d/m/Y'));
            $ws->setCellValue("D$r", $dep->amount);
            $ws->setCellValue("E$r", $dep->reference_no ? 'Ref: ' . $dep->reference_no : '');
            $this->money($ws, "D$r");
            $this->rowBg($ws, $r, 1, 5, 'E3F2FD');
            $ws->getStyle("D$r")->getFont()->getColor()->setARGB('FF1565C0');
            $r++;

            if ($dep->admin_fee > 0) {
                $ws->setCellValue("A$r", '-');
                $ws->setCellValue("B$r", '   └ Admin TF Penampung');
                $ws->setCellValue("C$r", $dep->deposit_date->format('d/m/Y'));
                $ws->setCellValue("D$r", $dep->admin_fee);
                $ws->setCellValue("E$r", 'Biaya admin transfer');
                $this->money($ws, "D$r");
                $this->rowBg($ws, $r, 1, 5, 'E3F2FD');
                $ws->getStyle("D$r")->getFont()->getColor()->setARGB('FF42A5F5');
                $r++;
            }
        }

        // Penjualan (uang masuk)
        $salesByDay = Distribution::where('period_id', $period->id)
            ->selectRaw('dist_date, SUM(qty * price_per_unit) as total')
            ->groupBy('dist_date')->orderBy('dist_date')->get();

        $r++;
        $ws->setCellValue("B$r", '── UANG MASUK ──');
        $ws->getStyle("B$r")->getFont()->setBold(true)->getColor()->setARGB('FF2E7D32');
        $r++;

        foreach ($salesByDay as $sd) {
            $ws->setCellValue("A$r", '-');
            $ws->setCellValue("B$r", 'Penjualan Gas');
            $ws->setCellValue("C$r", \Carbon\Carbon::parse($sd->dist_date)->format('d/m/Y'));
            $ws->setCellValue("D$r", $sd->total);
            $this->money($ws, "D$r");
            $this->rowBg($ws, $r, 1, 5, 'E8F5E9');
            $ws->getStyle("D$r")->getFont()->getColor()->setARGB('FF2E7D32');
            $r++;
        }

        $this->setBorders($ws, "A2:E$r");
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. SHEET TRANSFER & SETORAN
    // ═══════════════════════════════════════════════════════════════════
    private function sheetTransfer(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '5. Transfer & Setoran');
        foreach (['A','B','C','D','E','F','G','H'] as $col) {
            $ws->getColumnDimension($col)->setWidth(16);
        }
        $ws->getColumnDimension('H')->setWidth(35);

        $this->styleTitle($ws, 'A1', "TRANSFER & SETORAN — {$period->label}");
        $ws->mergeCells('A1:H1');

        // Setoran Kurir
        $r = 3;
        $ws->setCellValue("A$r", 'SETORAN KURIR → REKENING PENAMPUNG');
        $ws->mergeCells("A$r:H$r");
        $ws->getStyle("A$r")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FF1565C0');
        $r++;

        foreach (['No','Kurir','Tanggal','Nominal','Admin','Bersih','No. Ref','Catatan'] as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1);
            $ws->setCellValue("{$col}$r", $h);
        }
        $this->styleHeader($ws, "A$r:H$r", 'FF1565C0');
        $r++;

        $depStart = $r;
        $deposits = CourierDeposit::with('courier')->where('period_id', $period->id)->orderBy('deposit_date')->get();
        foreach ($deposits as $i => $dep) {
            $ws->setCellValue("A$r", $i+1);
            $ws->setCellValue("B$r", $dep->courier->name);
            $ws->setCellValue("C$r", $dep->deposit_date->format('d/m/Y'));
            $ws->setCellValue("D$r", $dep->amount);
            $ws->setCellValue("E$r", $dep->admin_fee);
            $ws->setCellValue("F$r", $dep->net_amount);
            $ws->setCellValue("G$r", $dep->reference_no);
            $ws->setCellValue("H$r", $dep->notes);
            $this->money($ws, "D$r:F$r");
            $r++;
        }
        if ($deposits->count() > 0) {
            $ws->setCellValue("C$r", 'TOTAL');
            $ws->setCellValue("D$r", "=SUM(D{$depStart}:D".($r-1).")");
            $ws->setCellValue("E$r", "=SUM(E{$depStart}:E".($r-1).")");
            $ws->setCellValue("F$r", "=SUM(F{$depStart}:F".($r-1).")");
            $this->styleHeader($ws, "A$r:H$r", 'FF1565C0');
            $this->money($ws, "D$r:F$r");
            $r++;
        }

        // Transfer ke Rek Utama
        $r += 2;
        $ws->setCellValue("A$r", 'TRANSFER PENAMPUNG → REKENING UTAMA');
        $ws->mergeCells("A$r:H$r");
        $ws->getStyle("A$r")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FF4A148C');
        $r++;

        foreach (['No','Tanggal','Nominal','Setara DO','Surplus','DO Dilunasi','','Catatan'] as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1);
            $ws->setCellValue("{$col}$r", $h);
        }
        $this->styleHeader($ws, "A$r:H$r", 'FF4A148C');
        $r++;

        $tfStart = $r;
        $transfers = AccountTransfer::with('deliveryOrders.outlet')
            ->where('period_id', $period->id)->orderBy('transfer_date')->get();

        foreach ($transfers as $i => $tf) {
            $doNames = $tf->deliveryOrders->map(fn($d) =>
                $d->outlet->name . ' Rp' . number_format($d->pivot->amount_allocated/1000) . 'k'
            )->join(', ');

            $ws->setCellValue("A$r", $i+1);
            $ws->setCellValue("B$r", $tf->transfer_date->format('d/m/Y'));
            $ws->setCellValue("C$r", $tf->amount);
            $ws->setCellValue("D$r", $tf->do_equivalent_qty . ' tab');
            $ws->setCellValue("E$r", $tf->surplus);
            $ws->setCellValue("F$r", $doNames ?: '-');
            $ws->setCellValue("H$r", $tf->notes);
            $this->money($ws, "C$r:E$r");
            if ($tf->surplus > 0) {
                $ws->getStyle("E$r")->getFont()->getColor()->setARGB('FF2E7D32');
            }
            $r++;
        }
        if ($transfers->count() > 0) {
            $ws->setCellValue("B$r", 'TOTAL');
            $ws->setCellValue("C$r", "=SUM(C{$tfStart}:C".($r-1).")");
            $ws->setCellValue("E$r", "=SUM(E{$tfStart}:E".($r-1).")");
            $this->styleHeader($ws, "A$r:H$r", 'FF4A148C');
            $this->money($ws, "C$r:E$r");
        }

        $this->setBorders($ws, "A2:H$r");
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. SHEET TABUNGAN
    // ═══════════════════════════════════════════════════════════════════
    private function sheetTabungan(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '6. Tabungan');
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(13);
        $ws->getColumnDimension('C')->setWidth(10);
        $ws->getColumnDimension('D')->setWidth(16);
        $ws->getColumnDimension('E')->setWidth(16);
        $ws->getColumnDimension('F')->setWidth(18);
        $ws->getColumnDimension('G')->setWidth(14);
        $ws->getColumnDimension('H')->setWidth(35);

        $this->styleTitle($ws, 'A1', "TABUNGAN / SURPLUS — {$period->label}");
        $ws->mergeCells('A1:H1');

        $r = 3;
        $ws->setCellValue("B$r", 'Saldo Awal (Cutoff)');
        $ws->setCellValue("D$r", $period->opening_surplus ?? 0);
        $ws->getStyle("B$r")->getFont()->setBold(true);
        $ws->getStyle("D$r")->getFont()->setBold(true)->getColor()->setARGB('FF827717');
        $this->money($ws, "D$r");
        $r += 2;

        foreach (['No','Tanggal','Jenis','Masuk (Rp)','Keluar (Rp)','Saldo (Rp)','Sumber','Keterangan'] as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1);
            $ws->setCellValue("{$col}$r", $h);
        }
        $this->styleHeader($ws, "A$r:H$r", 'FF827717');
        $r++;

        $savings = Saving::where('period_id', $period->id)->orderBy('entry_date')->orderBy('id')->get();
        $running = (int)($period->opening_surplus ?? 0);

        foreach ($savings as $i => $s) {
            $running += $s->type === 'in' ? $s->amount : -$s->amount;
            $ws->setCellValue("A$r", $i+1);
            $ws->setCellValue("B$r", $s->entry_date->format('d/m/Y'));
            $ws->setCellValue("C$r", $s->type === 'in' ? '↓ Masuk' : '↑ Keluar');
            $ws->setCellValue("D$r", $s->type === 'in' ? $s->amount : 0);
            $ws->setCellValue("E$r", $s->type === 'out' ? $s->amount : 0);
            $ws->setCellValue("F$r", $running);
            $ws->setCellValue("G$r", $s->account_transfer_id ? 'Transfer Otomatis' : 'Manual');
            $ws->setCellValue("H$r", $s->description);
            $this->money($ws, "D$r:F$r");
            $bg = $s->type === 'in' ? 'E8F5E9' : 'FFEBEE';
            $this->rowBg($ws, $r, 1, 8, $bg);
            $r++;
        }

        $totalIn  = $savings->where('type','in')->sum('amount');
        $totalOut = $savings->where('type','out')->sum('amount');
        $balance  = ($period->opening_surplus ?? 0) + $totalIn - $totalOut;

        $ws->setCellValue("C$r", 'SALDO AKHIR');
        $ws->setCellValue("D$r", $totalIn);
        $ws->setCellValue("E$r", $totalOut);
        $ws->setCellValue("F$r", $balance);
        $this->styleHeader($ws, "A$r:H$r", 'FF827717');
        $this->money($ws, "D$r:F$r");

        $this->setBorders($ws, "A2:H$r");
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. SHEET PIUTANG EXTERNAL
    // ═══════════════════════════════════════════════════════════════════
    private function sheetPiutangExternal(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '7. Piutang External');
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(13);
        $ws->getColumnDimension('C')->setWidth(10);
        $ws->getColumnDimension('D')->setWidth(20);
        $ws->getColumnDimension('E')->setWidth(16);
        $ws->getColumnDimension('F')->setWidth(35);

        $this->styleTitle($ws, 'A1', "PIUTANG EXTERNAL / MODAL — {$period->label}");
        $ws->mergeCells('A1:F1');

        $r = 3;
        $ws->setCellValue("B$r", 'Saldo Awal');
        $ws->setCellValue("E$r", $period->opening_external_debt);
        $ws->getStyle("B$r")->getFont()->setBold(true);
        $this->money($ws, "E$r");
        $r += 2;

        foreach (['No','Tanggal','Jenis','Pihak','Nominal (Rp)','Keterangan'] as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1);
            $ws->setCellValue("{$col}$r", $h);
        }
        $this->styleHeader($ws, "A$r:F$r", 'FF4A148C');
        $r++;

        $debts = ExternalDebt::where('period_id', $period->id)->orderBy('entry_date')->get();
        foreach ($debts as $i => $d) {
            $ws->setCellValue("A$r", $i+1);
            $ws->setCellValue("B$r", $d->entry_date->format('d/m/Y'));
            $ws->setCellValue("C$r", $d->type === 'in' ? '↓ Masuk' : '↑ Keluar');
            $ws->setCellValue("D$r", $d->source_name);
            $ws->setCellValue("E$r", $d->amount);
            $ws->setCellValue("F$r", $d->description);
            $this->money($ws, "E$r");
            $this->rowBg($ws, $r, 1, 6, $d->type === 'in' ? 'E8F5E9' : 'FFEBEE');
            $r++;
        }

        $totalIn  = $debts->where('type','in')->sum('amount');
        $totalOut = $debts->where('type','out')->sum('amount');
        $balance  = $period->opening_external_debt + $totalIn - $totalOut;

        $ws->setCellValue("C$r", 'Saldo Akhir');
        $ws->setCellValue("E$r", $balance);
        $this->styleHeader($ws, "A$r:F$r", 'FF4A148C');
        $this->money($ws, "E$r");
        $ws->getStyle("E$r")->getFont()->getColor()->setARGB('FFFFFFFF');

        $this->setBorders($ws, "A2:F$r");
    }

    // ═══════════════════════════════════════════════════════════════════
    // 8. SHEET KONTRAK PANGKALAN
    // ═══════════════════════════════════════════════════════════════════
    private function sheetKontrakPangkalan(Spreadsheet $wb, Period $period): void
    {
        $ws = $this->newSheet($wb, '8. Kontrak Pangkalan');
        foreach (['A','B','C','D','E','F','G'] as $col) {
            $ws->getColumnDimension($col)->setWidth(16);
        }
        $ws->getColumnDimension('G')->setWidth(35);

        $this->styleTitle($ws, 'A1', "KONTRAK PANGKALAN — {$period->label}");
        $ws->mergeCells('A1:G1');

        $r = 3;
        foreach (['No','Pangkalan','Tipe Kontrak','Tarif','Nilai (Rp)','Terbayar','Status'] as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1);
            $ws->setCellValue("{$col}$r", $h);
        }
        $this->styleHeader($ws, "A$r:G$r");
        $r++;

        $payments = OutletContractPayment::with('outlet')
            ->where('period_id', $period->id)->get();

        foreach ($payments as $i => $cp) {
            $ws->setCellValue("A$r", $i+1);
            $ws->setCellValue("B$r", $cp->outlet->name);
            $ws->setCellValue("C$r", match($cp->outlet->contract_type) {
                'per_do'       => 'Per DO (×' . number_format($cp->outlet->contract_rate) . '/tab)',
                'flat_monthly' => 'Flat Bulanan',
                default        => '-',
            });
            $ws->setCellValue("D$r", $cp->outlet->contract_rate);
            $ws->setCellValue("E$r", $cp->calculated_amount);
            $ws->setCellValue("F$r", $cp->paid_amount);
            $ws->setCellValue("G$r", $cp->status === 'paid' ? 'Lunas' : 'Belum Lunas');
            $this->money($ws, "D$r:F$r");
            $bg = $cp->status === 'paid' ? 'E8F5E9' : 'FFEBEE';
            $this->rowBg($ws, $r, 1, 7, $bg);
            $r++;
        }

        // Gaji Kurir
        $r += 2;
        $ws->setCellValue("A$r", 'GAJI KURIR');
        $ws->mergeCells("A$r:G$r");
        $ws->getStyle("A$r")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFD35400');
        $r++;

        foreach (['No','Kurir','Upah/Tabung','Total Distribusi','Total Gaji','',''] as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1);
            $ws->setCellValue("{$col}$r", $h);
        }
        $this->styleHeader($ws, "A$r:G$r");
        $r++;

        $couriers = Courier::where('is_active', true)->get();
        foreach ($couriers as $i => $c) {
            $qty  = Distribution::where('period_id', $period->id)->where('courier_id', $c->id)->sum('qty');
            $wage = $qty * $c->wage_per_unit;
            $ws->setCellValue("A$r", $i+1);
            $ws->setCellValue("B$r", $c->name);
            $ws->setCellValue("C$r", $c->wage_per_unit);
            $ws->setCellValue("D$r", $qty . ' tabung');
            $ws->setCellValue("E$r", $wage);
            $this->money($ws, "C$r");
            $this->money($ws, "E$r");
            $r++;
        }

        $this->setBorders($ws, "A2:G$r");
    }
}
