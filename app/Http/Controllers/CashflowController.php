<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\CourierDeposit;
use App\Models\DailyExpense;
use App\Models\Distribution;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CashflowController extends Controller
{
    const HPP_PER_TABUNG = 16000;

    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period   = Period::findOrFail($periodId);
        $periods  = Period::orderByDesc('year')->orderByDesc('month')->get();

        $expenses = DailyExpense::where('period_id', $period->id)
            ->orderBy('expense_date')->orderBy('category')->get();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);

        $categories     = array_keys(DailyExpense::$categoryLabels);
        $expenseGrid    = [];
        $categoryTotals = array_fill_keys($categories, 0);
        $dayTotals      = array_fill(1, $daysInMonth, 0);

        foreach ($expenses as $e) {
            $day = $e->expense_date->day;
            $expenseGrid[$e->category][$day] = ($expenseGrid[$e->category][$day] ?? 0) + $e->amount;
            $categoryTotals[$e->category]    += $e->amount;
            $dayTotals[$day]                 += $e->amount;
        }

        $salesByDay = Distribution::where('period_id', $period->id)
            ->selectRaw('DAY(dist_date) as day, SUM(paid_amount) as total')
            ->groupBy('day')->pluck('total', 'day')->toArray();

        $marginByDay = Distribution::where('period_id', $period->id)
            ->where('paid_amount', '>', 0)
            ->selectRaw('
                DAY(dist_date) as day,
                SUM(paid_amount) as gross,
                SUM(qty) as total_qty,
                SUM(paid_amount - (qty * ?)) as margin
            ', [self::HPP_PER_TABUNG])
            ->groupBy('day')
            ->get()
            ->keyBy('day')
            ->map(fn($r) => [
                'gross'  => (int) $r->gross,
                'qty'    => (int) $r->total_qty,
                'margin' => max(0, (int) $r->margin),
            ])
            ->toArray();

        $depositsByDay = CourierDeposit::where('period_id', $period->id)
            ->selectRaw('DAY(deposit_date) as day, SUM(amount) as total, SUM(admin_fee) as total_admin')
            ->groupBy('day')->get()->keyBy('day')
            ->map(fn($r) => ['total' => (int)$r->total, 'admin' => (int)$r->total_admin])->toArray();

        $totalDeposits  = array_sum(array_column($depositsByDay, 'total'));
        $totalAdminFees = array_sum(array_column($depositsByDay, 'admin'));

        $transfersByDay = AccountTransfer::where('period_id', $period->id)
            ->selectRaw('DAY(transfer_date) as day, SUM(amount) as total, SUM(surplus) as total_surplus')
            ->groupBy('day')->get()->keyBy('day')
            ->map(fn($r) => ['total' => (int)$r->total, 'surplus' => (int)$r->total_surplus])->toArray();

        $totalTransferred = array_sum(array_column($transfersByDay, 'total'));

        $openingCash      = (int) $period->opening_cash;
        $openingPenampung = (int) $period->opening_penampung;

        $dailyBalance = [];
        $runningCash  = $openingCash;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $runningCash += ($salesByDay[$day] ?? 0)
                - ($dayTotals[$day] ?? 0)
                - ($depositsByDay[$day]['total'] ?? 0)
                - ($depositsByDay[$day]['admin'] ?? 0);
            $dailyBalance[$day] = $runningCash;
        }

        $dailyBankBalance = [];
        $runningBank = $openingPenampung;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $runningBank += ($depositsByDay[$day]['total'] ?? 0)
                - ($transfersByDay[$day]['total'] ?? 0);
            $dailyBankBalance[$day] = $runningBank;
        }

        $totalExpense = array_sum($categoryTotals);
        $totalIncome  = array_sum($salesByDay);
        $totalMargin  = array_sum(array_column($marginByDay, 'margin'));

        $distribData = Distribution::where('period_id', $period->id)
            ->where('paid_amount', '>', 0)
            ->selectRaw('SUM(qty) as total_qty, COUNT(DISTINCT DAY(dist_date)) as active_days')
            ->first();

        $totalTabungAktual = (int)($distribData->total_qty ?? 0);
        $distribActiveDays = (int)($distribData->active_days ?? 1);
        $avgTabungPerHari  = $distribActiveDays > 0 ? $totalTabungAktual / $distribActiveDays : 0;

        $piutangBelumBayar = (int)(Distribution::where('period_id', $period->id)
            ->whereIn('payment_status', ['deferred','partial'])
            ->selectRaw('SUM(total_value - paid_amount) as total')
            ->value('total') ?? 0);

        $piutangMarginBelumBayar = (int)(Distribution::where('period_id', $period->id)
            ->whereIn('payment_status', ['deferred','partial'])
            ->where('paid_amount', '<', \DB::raw('total_value'))
            ->selectRaw('SUM((total_value - paid_amount) * (price_per_unit - ?) / price_per_unit) as total', [self::HPP_PER_TABUNG])
            ->value('total') ?? 0);
        $piutangMarginBelumBayar = max(0, $piutangMarginBelumBayar);

        // Hitung net KAS & bank akhir periode (posisi aktual terakhir)
        $netKas       = $dailyBalance[$daysInMonth];
        $finalBankBal = $dailyBankBalance[$daysInMonth];

        $pred = $this->buildPrediction(
            period: $period,
            daysInMonth: $daysInMonth,
            salesByDay: $salesByDay,
            marginByDay: $marginByDay,
            dayTotals: $dayTotals,
            depositsByDay: $depositsByDay,
            transfersByDay: $transfersByDay,
            totalDeposits: $totalDeposits,
            totalAdminFees: $totalAdminFees,
            totalTransferred: $totalTransferred,
            totalExpense: $totalExpense,
            totalIncome: $totalIncome,
            totalMargin: $totalMargin,
            openingCash: $openingCash,
            openingPenampung: $openingPenampung,
            netKas: $netKas,
            finalBankBal: $finalBankBal,
            totalTabungAktual: $totalTabungAktual,
            avgTabungPerHari: $avgTabungPerHari,
            piutangBelumBayar: $piutangBelumBayar,
            piutangMarginBelumBayar: $piutangMarginBelumBayar,
        );

        return view('cashflow.index1', compact(
            'period','periods','expenses','categories',
            'expenseGrid','categoryTotals','dayTotals',
            'salesByDay','marginByDay','daysInMonth',
            'totalExpense','totalIncome','totalMargin',
            'depositsByDay','totalDeposits','totalAdminFees',
            'transfersByDay','totalTransferred',
            'openingCash','openingPenampung',
            'dailyBalance','dailyBankBalance',
            'netKas','finalBankBal',
            'totalTabungAktual','avgTabungPerHari',
            'piutangBelumBayar','piutangMarginBelumBayar',
            'pred'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PREDIKSI ENGINE v4.2
    //
    // Perbaikan vs v4.1:
    // - Semua proyeksi berbasis MARGIN BERSIH secara konsisten
    //   (bukan mixed gross/margin)
    // - predBank: pakai net rate (deposit - transfer) — rekening transit
    // - predRasio & rasioAktual: basis margin bersih (konsisten)
    // - adminRate: masuk sebagai komponen terpisah yang nyata
    // - projDep: tidak lagi 0-placeholder, dihapus dari kalkulasi
    // - gajiAktualSdIni dihitung dari tabung aktual × 500
    // ═══════════════════════════════════════════════════════════════════════
    private function buildPrediction(
        Period $period, int $daysInMonth,
        array $salesByDay, array $marginByDay, array $dayTotals,
        array $depositsByDay, array $transfersByDay,
        int $totalDeposits, int $totalAdminFees, int $totalTransferred,
        int $totalExpense, int $totalIncome, int $totalMargin,
        int $openingCash, int $openingPenampung,
        int $netKas, int $finalBankBal,
        int $totalTabungAktual, float $avgTabungPerHari,
        int $piutangBelumBayar, int $piutangMarginBelumBayar,
    ): array {
        $now         = now();
        $periodYear  = (int) Carbon::parse($period->start_date)->format('Y');
        $periodMonth = (int) Carbon::parse($period->start_date)->format('m');
        $periodEnd   = Carbon::create($periodYear, $periodMonth, $daysInMonth)->endOfDay();

        if ($period->status === 'closed' || $now->gt($periodEnd)) {
            $today = $daysInMonth; $remainDays = 0;
        } elseif ($periodYear === (int)$now->format('Y') && $periodMonth === (int)$now->format('m')) {
            $today = $now->day; $remainDays = $daysInMonth - $today;
        } else {
            $today = 0; $remainDays = $daysInMonth;
        }

        $progressPct = $daysInMonth > 0 ? round($today / $daysInMonth * 100) : 0;

        // ── Hari aktif ───────────────────────────────────────────────────────
        $marginActiveDays = collect(range(1, max(1, $today)))
            ->filter(fn($d) => ($marginByDay[$d]['margin'] ?? 0) > 0)->count() ?: 1;
        $expActiveDays    = collect(range(1, max(1, $today)))
            ->filter(fn($d) => ($dayTotals[$d] ?? 0) > 0)->count() ?: 1;
        $depActiveDays    = collect(range(1, max(1, $today)))
            ->filter(fn($d) => ($depositsByDay[$d]['total'] ?? 0) > 0)->count() ?: 1;
        $tfActiveDays     = collect(range(1, max(1, $today)))
            ->filter(fn($d) => ($transfersByDay[$d]['total'] ?? 0) > 0)->count() ?: 1;

        // ── WMA 5 hari (bobot 2:1) ────────────────────────────────────────────
        // SEMUA berbasis MARGIN BERSIH agar proyeksi kas konsisten
        $last5 = collect(range(max(1, $today - 4), max(1, $today)));
        $prev5 = collect(range(max(1, $today - 9), max(1, $today - 5)));

        $avgMarginLast5 = $last5->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0);
        $avgMarginPrev5 = $prev5->count() > 0
            ? $prev5->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0) : $avgMarginLast5;

        $avgExpLast5 = $last5->avg(fn($d) => $dayTotals[$d] ?? 0);
        $avgExpPrev5 = $prev5->count() > 0
            ? $prev5->avg(fn($d) => $dayTotals[$d] ?? 0) : $avgExpLast5;

        // Admin fee: rata sederhana (jarang berubah drastis)
        $adminRateSimple  = $totalAdminFees / $depActiveDays;

        // Rate margin WMA (bobot 2 untuk 5 hari terakhir)
        $marginRateWma    = ($avgMarginLast5 * 2 + $avgMarginPrev5) / 3;
        $marginRateSimple = $marginActiveDays > 0 ? $totalMargin / $marginActiveDays : 0;

        // Rate pengeluaran WMA
        $expRateWma    = ($avgExpLast5 * 2 + $avgExpPrev5) / 3;
        $expRateSimple = $expActiveDays > 0 ? $totalExpense / $expActiveDays : 0;

        // Rate final: 60% WMA + 40% simpel (blended)
        $marginRate = ($marginRateWma * 0.6) + ($marginRateSimple * 0.4);
        $expRate    = ($expRateWma * 0.6)    + ($expRateSimple * 0.4);

        // ── Momentum 7 hari ──────────────────────────────────────────────────
        $last7 = collect(range(max(1, $today - 6), max(1, $today)));
        $prev7 = collect(range(max(1, $today - 13), max(1, $today - 7)));

        $avgMarginLast7 = $last7->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0);
        $avgMarginPrev7 = $prev7->count() > 0
            ? $prev7->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0) : $avgMarginLast7;

        $avgExpLast7 = $last7->avg(fn($d) => $dayTotals[$d] ?? 0);
        $avgExpPrev7 = $prev7->count() > 0
            ? $prev7->avg(fn($d) => $dayTotals[$d] ?? 0) : $avgExpLast7;

        $salesMomentum = $avgMarginPrev7 > 0
            ? round(($avgMarginLast7 - $avgMarginPrev7) / $avgMarginPrev7 * 100, 1) : 0;
        $expMomentum   = $avgExpPrev7 > 0
            ? round(($avgExpLast7 - $avgExpPrev7) / $avgExpPrev7 * 100, 1) : 0;

        // ── Faktor konservatif adaptif ────────────────────────────────────────
        // Basis 0.88, digeser oleh tren margin (max ±0.10)
        $baseCons = 0.88;
        $trendAdj = min(0.10, max(-0.10, $salesMomentum / 150));
        $conserv  = round($baseCons + $trendAdj, 4);

        // ── Proyeksi skenario UTAMA (konservatif) ─────────────────────────────
        // Margin proyeksi = rate × faktor konservatif × sisa hari
        $projMargin = round($marginRate * $conserv * $remainDays);
        // Pengeluaran proyeksi: TIDAK dikali conserv karena biaya cenderung tetap/naik
        $projExp    = round($expRate * $remainDays);
        // Admin fee proyeksi: proporsional ke sisa hari
        $projAdmin  = round($adminRateSimple * $remainDays);

        // Piutang: hanya 30% diasumsikan cair
        $piutangCairEstimasi = round($piutangMarginBelumBayar * 0.30);

        // ── Proyeksi skenario OPTIMIS & PESIMIS ───────────────────────────────
        // Optimis: margin +10%, expense -5%
        $projMarginOptim = round($marginRate * min($conserv + 0.10, 1.05) * $remainDays);
        $projExpOptim    = round($expRate * 0.95 * $remainDays);
        $projAdminOptim  = $projAdmin;

        // Pesimis: margin -20%, expense +10%
        $projMarginPesim = round($marginRate * max($conserv - 0.20, 0.60) * $remainDays);
        $projExpPesim    = round($expRate * 1.10 * $remainDays);
        $projAdminPesim  = $projAdmin;

        // ── Gaji kurir (dibayar tgl 1 bulan depan) ────────────────────────────
        $gajiPerTabung   = 500;
        $gajiAktualSdIni = $totalTabungAktual * $gajiPerTabung;
        $predTabungSisa  = round($avgTabungPerHari * $remainDays);
        $predGajiSisa    = $predTabungSisa * $gajiPerTabung;
        $predGajiTotal   = $gajiAktualSdIni + $predGajiSisa;

        $gajiOptim = ($totalTabungAktual + round($avgTabungPerHari * 1.05 * $remainDays)) * $gajiPerTabung;
        $gajiPesim = ($totalTabungAktual + round($avgTabungPerHari * 0.90 * $remainDays)) * $gajiPerTabung;

        // ── Prediksi saldo KAS akhir bulan ────────────────────────────────────
        // Formula: KAS kini + proyeksi margin + piutang cair - proyeksi ops - proyeksi admin
        // Catatan: deposit ke penampung sudah TERJADI bersamaan penjualan (bukan pengeluaran terpisah)
        // netKas sudah merefleksikan semua deposit yang sudah terjadi
        $predKas = $netKas
            + $projMargin
            + $piutangCairEstimasi
            - $projExp
            - $projAdmin;

        $predKasSetelahGaji = $predKas - $predGajiTotal;

        $predKasOptim = $netKas
            + $projMarginOptim
            - $projExpOptim
            - $projAdminOptim;

        $predKasPesim = $netKas
            + $projMarginPesim
            - $projExpPesim
            - $projAdminPesim;

        // ── Prediksi saldo BANK penampung ─────────────────────────────────────
        // Penampung = rekening TRANSIT. Hampir semua deposit langsung di-TF ke agen.
        // Net rate = deposit harian - transfer harian ≈ kecil
        $grossDepRate = $depActiveDays > 0 ? $totalDeposits / $depActiveDays : 0;
        $grossTfRate  = $tfActiveDays  > 0 ? $totalTransferred / $tfActiveDays : 0;
        $netBankRate  = $grossDepRate - $grossTfRate;
        // Bank prediksi = posisi kini + akumulasi net rate sisa bulan
        $predBank = $finalBankBal + round($netBankRate * $remainDays);

        // ── Rasio biaya operasional vs MARGIN BERSIH ──────────────────────────
        // Rasio bermakna untuk bisnis gas ini:
        //   biaya ops / margin bersih — bukan vs gross omzet
        // Contoh: ops 1.4jt / margin 7jt = 20% = sehat
        $rasioAktual = $totalMargin > 0
            ? round($totalExpense / $totalMargin * 100, 1) : 0;

        // Proyeksi akhir bulan: (total ops s/d kini + proyeksi ops) / (margin s/d kini + proyeksi margin)
        $predTotalMarginFull = $totalMargin + $projMargin + $piutangCairEstimasi;
        $predTotalExpFull    = $totalExpense + $projExp;
        $predRasio = $predTotalMarginFull > 0
            ? round($predTotalExpFull / $predTotalMarginFull * 100, 1) : 0;

        // ── Confidence ───────────────────────────────────────────────────────
        $confidence = 90;
        if ($today < 5)                                      $confidence -= 40;
        elseif ($today < 10)                                 $confidence -= 20;
        elseif ($today < 15)                                 $confidence -= 10;
        if (abs($salesMomentum) > 30)                        $confidence -= 15;
        elseif (abs($salesMomentum) > 20)                    $confidence -= 8;
        if (abs($expMomentum) > 30)                          $confidence -= 10;
        if ($piutangBelumBayar > $totalIncome * 0.3)         $confidence -= 8;
        if ($remainDays === 0)                               $confidence  = 100;
        $confidence = max(25, min(95, $confidence));

        // ── Helper Holt DES ───────────────────────────────────────────────────
$holtsRunDES = function(array $series, float $alpha, float $beta): array {
    $n = count($series);
    if ($n < 2) {
        $l = $series[0] ?? 0;
        $t = 0.0;
        return compact('l', 't', 'alpha', 'beta', 'sse');
    }
    // Inisialisasi: L0 = y(1), T0 = y(2) - y(1)
    $L = (float)$series[0];
    $T = (float)($series[1] - $series[0]);
    $sse = 0.0;
    for ($i = 1; $i < $n; $i++) {
        $yHat = $L + $T;
        $sse += ($series[$i] - $yHat) ** 2;
        $Lprev = $L;
        $L = $alpha * $series[$i] + (1 - $alpha) * ($L + $T);
        $T = $beta  * ($L - $Lprev) + (1 - $beta) * $T;
    }
    return ['l' => $L, 't' => $T, 'alpha' => $alpha, 'beta' => $beta, 'sse' => $sse];
};

// ── Susun time-series per hari (hanya hari dengan data) ───────────────
$holtsMarginSeries = [];
$holtsExpSeries    = [];
$holtsAdminSeries  = [];
$holtsMarginDays   = [];  // hari ke-berapa dalam bulan (utk transparansi)

for ($d = 1; $d <= $today; $d++) {
    $margin = $marginByDay[$d]['margin'] ?? 0;
    $e      = $dayTotals[$d] ?? 0;
    $admin  = $depositsByDay[$d]['admin'] ?? 0;
    if ($margin > 0 || $e > 0) {
        $holtsMarginSeries[] = (float)$margin;
        $holtsExpSeries[]    = (float)$e;
        $holtsAdminSeries[]  = (float)$admin;
        $holtsMarginDays[]   = $d;
    }
}

$holtsDataPoints = count($holtsMarginSeries);

// ── Grid search optimasi α & β (SSE minimum) ────────────────────────
$alphas = [0.2, 0.3, 0.4, 0.5, 0.6];
$betas  = [0.1, 0.2, 0.3, 0.4, 0.5];

$bestMargin = ['sse' => PHP_FLOAT_MAX];
$bestExp    = ['sse' => PHP_FLOAT_MAX];

if ($holtsDataPoints >= 3) {
    foreach ($alphas as $a) {
        foreach ($betas as $b) {
            $resM = $holtsRunDES($holtsMarginSeries, $a, $b);
            $resE = $holtsRunDES($holtsExpSeries, $a, $b);
            if ($resM['sse'] < $bestMargin['sse']) $bestMargin = $resM;
            if ($resE['sse'] < $bestExp['sse'])    $bestExp    = $resE;
        }
    }
} elseif ($holtsDataPoints >= 2) {
    // Kurang data — pakai default α=0.4, β=0.2
    $bestMargin = $holtsRunDES($holtsMarginSeries, 0.40, 0.20);
    $bestExp    = $holtsRunDES($holtsExpSeries,    0.40, 0.20);
} else {
    // Tidak cukup data — fallback ke rate simpel
    $fallbackMargin = $marginRateSimple;
    $fallbackExp    = $expRateSimple;
    $bestMargin = ['l' => $fallbackMargin, 't' => 0.0, 'alpha' => 0.40, 'beta' => 0.20, 'sse' => 0];
    $bestExp    = ['l' => $fallbackExp,    't' => 0.0, 'alpha' => 0.40, 'beta' => 0.20, 'sse' => 0];
}

// ── Admin DES ─────────────────────────────────────────────────────────
$bestAdmin = $holtsDataPoints >= 2
    ? $holtsRunDES($holtsAdminSeries, 0.40, 0.20)
    : ['l' => $adminRateSimple, 't' => 0.0, 'alpha' => 0.40, 'beta' => 0.20, 'sse' => 0];

// ── Parameter akhir ───────────────────────────────────────────────────
$holtsAlphaMargin = $bestMargin['alpha'];
$holtsBetaMargin  = $bestMargin['beta'];
$holtsAlphaExp    = $bestExp['alpha'];
$holtsBetaExp     = $bestExp['beta'];

// Level & trend akhir dari data aktual
$holtsLevelMargin = $bestMargin['l'];
$holtsTrendMargin = $bestMargin['t'];
$holtsLevelExp    = $bestExp['l'];
$holtsTrendExp    = $bestExp['t'];
$holtsLevelAdmin  = $bestAdmin['l'];
$holtsTrendAdmin  = $bestAdmin['t'];

// ── Proyeksi sisa hari ────────────────────────────────────────────────
// ŷ(t+h) = L + h·T  →  floor ke 0 (tidak ada penjualan/biaya negatif)
$holtsProjMargin = 0;
$holtsProjExp    = 0;
$holtsProjAdmin  = 0;

for ($h = 1; $h <= $remainDays; $h++) {
    $holtsProjMargin += max(0, $holtsLevelMargin + $h * $holtsTrendMargin);
    $holtsProjExp    += max(0, $holtsLevelExp    + $h * $holtsTrendExp);
    $holtsProjAdmin  += max(0, $holtsLevelAdmin  + $h * $holtsTrendAdmin);
}
$holtsProjMargin = (int)round($holtsProjMargin);
$holtsProjExp    = (int)round($holtsProjExp);
$holtsProjAdmin  = (int)round($holtsProjAdmin);

// ── Prediksi saldo KAS (Holt DES) ─────────────────────────────────────
// Gaji & piutang sama dengan WMA/OLS untuk konsistensi
$holtsPredKas = $netKas
    + $holtsProjMargin
    + $piutangCairEstimasi
    - $holtsProjExp
    - $holtsProjAdmin
    - $predGajiTotal;

// 3 skenario (±1 std dev trend projection)
$holtsMarginStdPerDay = count($holtsMarginSeries) > 1
    ? (function(array $arr): float {
        $mean = array_sum($arr) / count($arr);
        $sq   = array_map(fn($v) => ($v - $mean) ** 2, $arr);
        return sqrt(array_sum($sq) / (count($arr) - 1));
    })($holtsMarginSeries)
    : abs($holtsLevelMargin) * 0.15;

$holtsExpStdPerDay = count($holtsExpSeries) > 1
    ? (function(array $arr): float {
        $mean = array_sum($arr) / count($arr);
        $sq   = array_map(fn($v) => ($v - $mean) ** 2, $arr);
        return sqrt(array_sum($sq) / (count($arr) - 1));
    })($holtsExpSeries)
    : abs($holtsLevelExp) * 0.15;

// Skenario: ±1.5 std dev × √remainDays (standard error proyeksi)
$holtsSEMargin = $holtsMarginStdPerDay * sqrt(max(1, $remainDays));
$holtsSEExp    = $holtsExpStdPerDay    * sqrt(max(1, $remainDays));

$holtsPredKasOptim = $netKas
    + $holtsProjMargin + $holtsSEMargin * 1.5
    + $piutangCairEstimasi
    - max(0, $holtsProjExp - $holtsSEExp * 1.0)
    - $holtsProjAdmin
    - $predGajiTotal;

$holtsPredKasPesim = $netKas
    + max(0, $holtsProjMargin - $holtsSEMargin * 1.5)
    + $piutangCairEstimasi
    - ($holtsProjExp + $holtsSEExp * 1.0)
    - $holtsProjAdmin
    - $predGajiTotal;

$holtsPredKasOptim = (int)round($holtsPredKasOptim);
$holtsPredKasPesim = (int)round($holtsPredKasPesim);

// Tren label
$holtsMarginTrend = $holtsTrendMargin >  500  ? '📈 Naik'
    : ($holtsTrendMargin < -500  ? '📉 Turun' : '➡ Stabil');
$holtsExpTrend    = $holtsTrendExp    >  100  ? '📈 Naik'
    : ($holtsTrendExp    < -100  ? '📉 Turun' : '➡ Stabil');

// MAE in-sample (akurasi fit)
$holtsMaeMargin = 0;
$holtsMaeExp    = 0;
if ($holtsDataPoints >= 2) {
    $Lm = (float)$holtsMarginSeries[0];
    $Tm = (float)($holtsMarginSeries[1] - $holtsMarginSeries[0]);
    $Le = (float)$holtsExpSeries[0];
    $Te = (float)($holtsExpSeries[1] - $holtsExpSeries[0]);
    $sumM = $sumE = 0;
    for ($i = 1; $i < $holtsDataPoints; $i++) {
        $yHatM = $Lm + $Tm;
        $yHatE = $Le + $Te;
        $sumM += abs($holtsMarginSeries[$i] - $yHatM);
        $sumE += abs($holtsExpSeries[$i]    - $yHatE);
        $Lmprev = $Lm; $Leprev = $Le;
        $Lm = $holtsAlphaMargin * $holtsMarginSeries[$i] + (1 - $holtsAlphaMargin) * ($Lm + $Tm);
        $Tm = $holtsBetaMargin  * ($Lm - $Lmprev) + (1 - $holtsBetaMargin) * $Tm;
        $Le = $holtsAlphaExp    * $holtsExpSeries[$i]    + (1 - $holtsAlphaExp)    * ($Le + $Te);
        $Te = $holtsBetaExp     * ($Le - $Leprev)        + (1 - $holtsBetaExp)     * $Te;
    }
    $holtsMaeMargin = (int)round($sumM / ($holtsDataPoints - 1));
    $holtsMaeExp    = (int)round($sumE / ($holtsDataPoints - 1));
}

// Confidence Holt DES
$holtsConfidence = min(95, round(
    min($holtsDataPoints, 20) / 20 * 40   // lebih banyak data = lebih yakin
    + ($holtsMaeMargin < $holtsLevelMargin * 0.2 ? 25 : 10)  // akurasi fit
    + ($holtsMaeExp    < $holtsLevelExp    * 0.3 ? 20 : 8)
    + ($holtsDataPoints >= 10 ? 10 : 0)
));
$holtsConfidence = max(20, min(92, $holtsConfidence));

        return compact(
            // Konteks waktu
            'today','remainDays','progressPct',
            // Rate harian
            'marginRate','marginRateWma','marginRateSimple',
            'expRate','expRateWma','expRateSimple',
            'adminRateSimple',
            // Momentum
            'salesMomentum','expMomentum',
            // Faktor
            'conserv',
            // Proyeksi komponen
            'projMargin','projExp','projAdmin',
            'projMarginOptim','projExpOptim','projAdminOptim',
            'projMarginPesim','projExpPesim','projAdminPesim',
            // Gaji
            'gajiPerTabung','gajiAktualSdIni','predTabungSisa','predGajiSisa','predGajiTotal',
            'gajiOptim','gajiPesim',
            // Piutang
            'piutangBelumBayar','piutangMarginBelumBayar','piutangCairEstimasi',
            // Prediksi saldo
            'predKas','predKasSetelahGaji','predKasOptim','predKasPesim',
            'predBank',
            // Rasio
            'predRasio','rasioAktual',
            // Confidence
            'confidence',
            'holtsAlphaMargin','holtsBetaMargin','holtsAlphaExp','holtsBetaExp',
            'holtsLevelMargin','holtsTrendMargin','holtsLevelExp','holtsTrendExp',
            'holtsLevelAdmin','holtsTrendAdmin',
            'holtsProjMargin','holtsProjExp','holtsProjAdmin',
            'holtsPredKas','holtsPredKasOptim','holtsPredKasPesim',
            'holtsMarginTrend','holtsExpTrend',
            'holtsMaeMargin','holtsMaeExp',
            'holtsDataPoints','holtsConfidence',
            'holtsSEMargin','holtsSEExp',
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'period_id'    => 'required|exists:periods,id',
            'expense_date' => 'required|date',
            'category'     => 'required|in:' . implode(',', array_keys(DailyExpense::$categoryLabels)),
            'amount'       => 'required|integer|min:1',
            'description'  => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);

        $period = Period::findOrFail($request->period_id);
        if ($period->status === 'closed') {
            return back()->with('error', 'Periode sudah ditutup.');
        }

        DailyExpense::create($request->only(
            'period_id','expense_date','category','amount','description','notes'
        ));

        return back()->with('success', 'Pengeluaran disimpan.');
    }

    public function update(Request $request, DailyExpense $expense)
    {
        $request->validate([
            'expense_date' => 'required|date',
            'category'     => 'required|in:' . implode(',', array_keys(DailyExpense::$categoryLabels)),
            'amount'       => 'required|integer|min:1',
            'description'  => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);

        $expense->update($request->only('expense_date','category','amount','description','notes'));
        return back()->with('success', 'Pengeluaran diupdate.');
    }

    public function destroy(DailyExpense $expense)
    {
        $periodId = $expense->period_id;
        $expense->delete();
        return redirect()->route('cashflow.index1', ['period_id' => $periodId])
            ->with('success', 'Pengeluaran dihapus.');
    }
}