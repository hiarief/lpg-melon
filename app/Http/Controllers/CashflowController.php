<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Models\CourierDeposit;
use App\Models\DailyExpense;
use App\Models\Distribution;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CashflowController extends Controller
{
    const HPP_PER_TABUNG = 16_000;
    const GAJI_PER_TABUNG = 500;
    const PIUTANG_CAIR_RATE = 0.30;   // 30% piutang diasumsikan cair

    // ═══════════════════════════════════════════════════════════════════════
    // INDEX
    // ═══════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $period  = Period::findOrFail($request->period_id ?? Period::current()?->id);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);
        $categories  = array_keys(DailyExpense::$categoryLabels);

        // ── Pengeluaran ─────────────────────────────────────────────────────
        $expenses       = DailyExpense::where('period_id', $period->id)
            ->orderBy('expense_date')->orderBy('category')->get();
        $expenseGrid    = [];
        $categoryTotals = array_fill_keys($categories, 0);
        $dayTotals      = array_fill(1, $daysInMonth, 0);

        foreach ($expenses as $e) {
            $day = $e->expense_date->day;
            $expenseGrid[$e->category][$day] = ($expenseGrid[$e->category][$day] ?? 0) + $e->amount;
            $categoryTotals[$e->category]    += $e->amount;
            $dayTotals[$day]                 += $e->amount;
        }

        // ── Penjualan & margin ──────────────────────────────────────────────
        $salesByDay = Distribution::where('period_id', $period->id)
            ->selectRaw('DAY(dist_date) as day, SUM(paid_amount) as total')
            ->groupBy('day')->pluck('total', 'day')->toArray();

        $marginByDay = Distribution::where('period_id', $period->id)
            ->where('paid_amount', '>', 0)
            ->selectRaw('
                DAY(dist_date)                               as day,
                SUM(paid_amount)                             as gross,
                SUM(qty)                                     as total_qty,
                SUM(paid_amount - (qty * ?))                 as margin
            ', [self::HPP_PER_TABUNG])
            ->groupBy('day')->get()->keyBy('day')
            ->map(fn($r) => [
                'gross'  => (int) $r->gross,
                'qty'    => (int) $r->total_qty,
                'margin' => max(0, (int) $r->margin),
            ])->toArray();

        // ── Deposit & transfer ──────────────────────────────────────────────
        $depositsByDay = CourierDeposit::where('period_id', $period->id)
            ->selectRaw('DAY(deposit_date) as day, SUM(amount) as total, SUM(admin_fee) as total_admin')
            ->groupBy('day')->get()->keyBy('day')
            ->map(fn($r) => ['total' => (int) $r->total, 'admin' => (int) $r->total_admin])
            ->toArray();

        $transfersByDay = AccountTransfer::where('period_id', $period->id)
            ->selectRaw('DAY(transfer_date) as day, SUM(amount) as total, SUM(surplus) as total_surplus')
            ->groupBy('day')->get()->keyBy('day')
            ->map(fn($r) => ['total' => (int) $r->total, 'surplus' => (int) $r->total_surplus])
            ->toArray();

        $totalDeposits   = (int) array_sum(array_column($depositsByDay, 'total'));
        $totalAdminFees  = (int) array_sum(array_column($depositsByDay, 'admin'));
        $totalTransferred = (int) array_sum(array_column($transfersByDay, 'total'));
        $totalSurplus     = (int) array_sum(array_column($transfersByDay, 'surplus'));

        // ── Saldo pembuka ───────────────────────────────────────────────────
        $openingCash      = (int) $period->opening_cash;
        $openingPenampung = (int) $period->opening_penampung;

        // ── Running balance harian ──────────────────────────────────────────
        [$dailyBalance, $dailyBankBalance] = $this->calcDailyBalances(
            $daysInMonth, $salesByDay, $dayTotals,
            $depositsByDay, $transfersByDay,
            $openingCash, $openingPenampung
        );

        // ── Agregat ─────────────────────────────────────────────────────────
        $totalExpense = (int) array_sum($categoryTotals);
        $totalIncome  = (int) array_sum($salesByDay);
        $totalMargin  = (int) array_sum(array_column($marginByDay, 'margin'));

        $netKas       = $dailyBalance[$daysInMonth];
        $finalBankBal = $dailyBankBalance[$daysInMonth];
        $netTotal     = $netKas + $finalBankBal;

        // ── Data tabung & piutang ───────────────────────────────────────────
        $distribData       = $this->getDistribAggregate($period);
        $totalTabungAktual = $distribData['total_qty'];
        $distribActiveDays = $distribData['active_days'];
        $avgTabungPerHari  = $distribActiveDays > 0 ? $totalTabungAktual / $distribActiveDays : 0;

        [$piutangBelumBayar, $piutangMarginBelumBayar] = $this->getPiutang($period);

        // ── Prediksi ────────────────────────────────────────────────────────
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
            totalSurplus: $totalSurplus,
            totalExpense: $totalExpense,
            totalIncome: $totalIncome,
            totalMargin: $totalMargin,
            openingCash: $openingCash,
            openingPenampung: $openingPenampung,
            netKas: $netKas,
            finalBankBal: $finalBankBal,
            netTotal: $netTotal,
            totalTabungAktual: $totalTabungAktual,
            avgTabungPerHari: $avgTabungPerHari,
            piutangBelumBayar: $piutangBelumBayar,
            piutangMarginBelumBayar: $piutangMarginBelumBayar,
        );

        // ── OLS (dihitung di controller, tidak di blade) ─────────────────────
        $ols = $this->calcOlsProjection(
            $pred['today'], $daysInMonth,
            $marginByDay, $dayTotals, $depositsByDay,
            $pred['adminRateSimple'],
            $pred['predGajiTotal'],
            $pred['piutangCairEstimasi'],
            $netKas
        );

        // ── MC params (dikirim ke JS) ─────────────────────────────────────
        $mc = $this->calcMonteCarloParams(
            $pred['today'], $marginByDay, $dayTotals, $depositsByDay
        );

        // ── View vars untuk chart JS ─────────────────────────────────────
        $jsLabels    = range(1, $daysInMonth);
        $chartData   = [
            'labels'    => $jsLabels,
            'sales'     => array_map(fn($d) => $salesByDay[$d] ?? 0, $jsLabels),
            'expenses'  => array_map(fn($d) => $dayTotals[$d]  ?? 0, $jsLabels),
            'deposits'  => array_map(fn($d) => $depositsByDay[$d]['total'] ?? 0, $jsLabels),
            'transfers' => array_map(fn($d) => $transfersByDay[$d]['total'] ?? 0, $jsLabels),
            'cashBal'   => array_map(fn($d) => $dailyBalance[$d], $jsLabels),
            'bankBal'   => array_map(fn($d) => $dailyBankBalance[$d], $jsLabels),
            'catLabels' => array_values(DailyExpense::$categoryLabels),
            'catTotals' => array_values($categoryTotals),
        ];

        // ── Ringkasan cashflow (dihitung di controller) ──────────────────────
        $summary = $this->buildSummary(
            $openingCash, $totalIncome, $totalExpense,
            $totalDeposits, $totalAdminFees, $totalMargin,
            $netKas, $finalBankBal, $netTotal,
            $dailyBalance, $dailyBankBalance,
            $salesByDay, $dayTotals, $depositsByDay,
            $daysInMonth
        );

        return view('cashflow.index', compact(
            'period', 'periods', 'expenses', 'categories',
            'expenseGrid', 'categoryTotals', 'dayTotals',
            'salesByDay', 'marginByDay', 'daysInMonth',
            'totalExpense', 'totalIncome', 'totalMargin',
            'depositsByDay', 'totalDeposits', 'totalAdminFees',
            'transfersByDay', 'totalTransferred','totalSurplus',
            'openingCash', 'openingPenampung',
            'dailyBalance', 'dailyBankBalance',
            'netKas', 'finalBankBal', 'netTotal',
            'totalTabungAktual', 'avgTabungPerHari',
            'piutangBelumBayar', 'piutangMarginBelumBayar',
            'pred', 'ols', 'mc', 'chartData', 'summary'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STORE / UPDATE / DESTROY
    // ═══════════════════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_id'    => 'required|exists:periods,id',
            'expense_date' => 'required|date',
            'category'     => 'required|in:' . implode(',', array_keys(DailyExpense::$categoryLabels)),
            'amount'       => 'required|integer|min:1',
            'description'  => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);

        $period = Period::findOrFail($validated['period_id']);
        abort_if($period->status === 'closed', 403, 'Periode sudah ditutup.');

        DailyExpense::create($validated);
        return back()->with('success', 'Pengeluaran disimpan.');
    }

    public function update(Request $request, DailyExpense $expense)
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'category'     => 'required|in:' . implode(',', array_keys(DailyExpense::$categoryLabels)),
            'amount'       => 'required|integer|min:1',
            'description'  => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);

        $expense->update($validated);
        return back()->with('success', 'Pengeluaran diupdate.');
    }

    public function destroy(DailyExpense $expense)
    {
        $periodId = $expense->period_id;
        $expense->delete();
        return redirect()->route('cashflow.index', ['period_id' => $periodId])
            ->with('success', 'Pengeluaran dihapus.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /** Hitung saldo KAS dan BANK per hari */
    private function calcDailyBalances(
        int $daysInMonth,
        array $salesByDay, array $dayTotals,
        array $depositsByDay, array $transfersByDay,
        int $openingCash, int $openingPenampung
    ): array {
        $cashBal = [];
        $bankBal = [];
        $runCash = $openingCash;
        $runBank = $openingPenampung;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $runCash += ($salesByDay[$day] ?? 0)
                - ($dayTotals[$day] ?? 0)
                - ($depositsByDay[$day]['total'] ?? 0)
                - ($depositsByDay[$day]['admin'] ?? 0);
            $runBank += ($depositsByDay[$day]['total'] ?? 0)
                - ($transfersByDay[$day]['total'] ?? 0);

            $cashBal[$day] = $runCash;
            $bankBal[$day] = $runBank;
        }

        return [$cashBal, $bankBal];
    }

    /** Ambil agregat distribusi (total tabung & hari aktif) */
    private function getDistribAggregate(Period $period): array
    {
        $row = Distribution::where('period_id', $period->id)
            ->where('paid_amount', '>', 0)
            ->selectRaw('SUM(qty) as total_qty, COUNT(DISTINCT DAY(dist_date)) as active_days')
            ->first();

        return [
            'total_qty'   => (int)($row->total_qty ?? 0),
            'active_days' => max(1, (int)($row->active_days ?? 1)),
        ];
    }

    /** Ambil total piutang (nominal & margin) */
    private function getPiutang(Period $period): array
    {
        $nominal = (int)(Distribution::where('period_id', $period->id)
            ->whereIn('payment_status', ['deferred', 'partial'])
            ->selectRaw('SUM(total_value - paid_amount) as total')
            ->value('total') ?? 0);

        $margin = (int)(Distribution::where('period_id', $period->id)
            ->whereIn('payment_status', ['deferred', 'partial'])
            ->where('paid_amount', '<', \DB::raw('total_value'))
            ->selectRaw('SUM((total_value - paid_amount) * (price_per_unit - ?) / price_per_unit) as total', [self::HPP_PER_TABUNG])
            ->value('total') ?? 0);

        return [$nominal, max(0, $margin)];
    }

    /** Ringkasan cashflow untuk blade (menghindari kalkulasi di view) */
    private function buildSummary(
        int $openingCash, int $totalIncome, int $totalExpense,
        int $totalDeposits, int $totalAdminFees, int $totalMargin,
        int $netKas, int $finalBankBal, int $netTotal,
        array $dailyBalance, array $dailyBankBalance,
        array $salesByDay, array $dayTotals, array $depositsByDay,
        int $daysInMonth
    ): array {
        $totalAvailable  = $openingCash + $totalIncome;
        $totalKasKeluar  = $totalExpense + $totalDeposits + $totalAdminFees;
        $totalSurplusAll = 0; // dihitung di luar jika diperlukan

        // Hari aktif
        $cfActiveDays = max(1, collect(range(1, $daysInMonth))->filter(fn($d) =>
            ($salesByDay[$d] ?? 0) > 0 || ($dayTotals[$d] ?? 0) > 0 ||
            ($depositsByDay[$d]['total'] ?? 0) > 0
        )->count());

        // Rata-rata per hari aktif
        $avg = fn(int $total) => round($total / $cfActiveDays);

        // Rata-rata saldo harian KAS
        $avgDailyCashBal = collect(range(1, $daysInMonth))
            ->filter(fn($d) => ($salesByDay[$d] ?? 0) > 0 || ($dayTotals[$d] ?? 0) > 0
                || ($depositsByDay[$d]['total'] ?? 0) > 0)
            ->map(fn($d) => $dailyBalance[$d])->avg() ?? 0;

        // Rata-rata saldo harian BANK
        $avgDailyBankBal = collect(range(1, $daysInMonth))
            ->filter(fn($d) => ($depositsByDay[$d]['total'] ?? 0) > 0)
            ->map(fn($d) => $dailyBankBalance[$d])->avg() ?? 0;

        // Rasio
        $rasioOperasional = $totalMargin > 0 ? $totalExpense / $totalMargin * 100 : 0;
        $rasioGross       = $totalAvailable > 0 ? $totalKasKeluar / $totalAvailable * 100 : 0;

        return [
            'totalAvailable'    => $totalAvailable,
            'totalKasKeluar'    => $totalKasKeluar,
            'cfActiveDays'      => $cfActiveDays,
            'avgSales'          => $avg($totalIncome),
            'avgExpense'        => $avg($totalExpense),
            'avgDeposit'        => $avg($totalDeposits),
            'avgAdminFee'       => $avg($totalAdminFees),
            'avgNet'            => $avg($netKas),
            'avgDailyCashBal'   => round($avgDailyCashBal),
            'avgDailyBankBal'   => round($avgDailyBankBal),
            'rasioOperasional'  => round($rasioOperasional, 1),
            'rasioGross'        => round($rasioGross, 1),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OLS — Ordinary Least Squares
    //
    // Dihitung di PHP, hasilnya dikirim ke view sebagai array $ols.
    // Blade hanya menampilkan nilai — tidak ada kalkulasi.
    // ═══════════════════════════════════════════════════════════════════════

    private function calcOlsProjection(
        int $today, int $daysInMonth,
        array $marginByDay, array $dayTotals, array $depositsByDay,
        float $adminRateSimple, int $predGajiTotal,
        int $piutangCairEstimasi, int $netKas
    ): array {
        $marginPairs = [];
        $expPairs    = [];

        for ($d = 1; $d <= $today; $d++) {
            $margin = $marginByDay[$d]['margin'] ?? 0;
            $e      = $dayTotals[$d] ?? 0;
            if ($margin > 0 || $e > 0 || ($depositsByDay[$d]['total'] ?? 0) > 0) {
                if ($margin > 0 || $e > 0) {
                    $marginPairs[] = [$d, $margin];
                    $expPairs[]    = [$d, $e];
                }
            }
        }

        [$b0m, $b1m, $r2m] = $this->ols($marginPairs);
        [$b0e, $b1e, $r2e] = $this->ols($expPairs);

        $remainDays = $daysInMonth - $today;

        $projMargin = 0;
        $projExp    = 0;
        $projAdmin  = 0;

        for ($d = $today + 1; $d <= $daysInMonth; $d++) {
            $projMargin += max(0, $b0m + $b1m * $d);
            $projExp    += max(0, $b0e + $b1e * $d);
            $projAdmin  += $adminRateSimple;
        }
        $projMargin = (int) round($projMargin);
        $projExp    = (int) round($projExp);
        $projAdmin  = (int) round($projAdmin);

        $predKas = $netKas + $projMargin + $piutangCairEstimasi
            - $projExp - $projAdmin - $predGajiTotal;

        $confidence = min(95, (int) round(
            $r2m * 40 + $r2e * 20 + min(count($marginPairs), 20) / 20 * 40
        ));

        return [
            // Koefisien margin
            'b0Margin'  => $b0m, 'b1Margin'  => $b1m, 'r2Margin'  => $r2m,
            'qualMargin' => $r2m >= 0.7 ? 'Baik' : ($r2m >= 0.4 ? 'Cukup' : 'Lemah'),
            'trendMargin' => $b1m > 500 ? '📈 Naik' : ($b1m < -500 ? '📉 Turun' : '➡ Stabil'),
            'dataPointsMargin' => count($marginPairs),
            // Koefisien expense
            'b0Exp'     => $b0e, 'b1Exp'     => $b1e, 'r2Exp'     => $r2e,
            'qualExp'   => $r2e >= 0.7 ? 'Baik' : ($r2e >= 0.4 ? 'Cukup' : 'Lemah'),
            'trendExp'  => $b1e > 100 ? '📈 Naik' : ($b1e < -100 ? '📉 Turun' : '➡ Stabil'),
            'dataPointsExp' => count($expPairs),
            // Proyeksi
            'projMargin' => $projMargin,
            'projExp'    => $projExp,
            'projAdmin'  => $projAdmin,
            // Prediksi saldo
            'predKas'    => (int) round($predKas),
            'confidence' => $confidence,
        ];
    }

    /**
     * OLS: kembalikan [b0, b1, r²]
     * b1 = (n·Σxy − Σx·Σy) / (n·Σx² − (Σx)²)
     */
    private function ols(array $pairs): array
    {
        $n = count($pairs);
        if ($n < 2) return [0, 0, 0];

        $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        foreach ($pairs as [$x, $y]) {
            $sumX  += $x;  $sumY  += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denom = $n * $sumX2 - $sumX * $sumX;
        if (abs($denom) < 1e-10) return [(int) round($sumY / $n), 0, 0];

        $b1   = ($n * $sumXY - $sumX * $sumY) / $denom;
        $b0   = ($sumY - $b1 * $sumX) / $n;
        $meanY = $sumY / $n;

        $ssTot = $ssRes = 0.0;
        foreach ($pairs as [$x, $y]) {
            $ssRes += ($y - ($b0 + $b1 * $x)) ** 2;
            $ssTot += ($y - $meanY) ** 2;
        }
        $r2 = $ssTot > 0 ? max(0.0, 1 - $ssRes / $ssTot) : 0.0;

        return [(int) round($b0), round($b1, 2), round($r2, 3)];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MONTE CARLO — parameter PHP, simulasi 10.000 iterasi di browser
    // ═══════════════════════════════════════════════════════════════════════

    private function calcMonteCarloParams(
        int $today,
        array $marginByDay, array $dayTotals, array $depositsByDay
    ): array {
        $marginData = [];
        $expData    = [];
        $adminData  = [];

        for ($d = 1; $d <= $today; $d++) {
            $margin = $marginByDay[$d]['margin'] ?? 0;
            $e      = $dayTotals[$d] ?? 0;
            if ($margin > 0 || $e > 0) {
                $marginData[] = (float) $margin;
                $expData[]    = (float) $e;
                $adminData[]  = (float)($depositsByDay[$d]['admin'] ?? 0);
            }
        }

        [$marginMean, $marginStd] = $this->meanStd($marginData);
        [$expMean,    $expStd]    = $this->meanStd($expData);
        [$adminMean,  $adminStd]  = $this->meanStd($adminData);

        // Korelasi margin–expense (Cholesky bivariate di JS)
        $n      = count($marginData);
        $corrCoef = 0.0;
        if ($n > 2 && $marginStd > 0 && $expStd > 0) {
            $covSum = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $covSum += ($marginData[$i] - $marginMean) * ($expData[$i] - $expMean);
            }
            $corrCoef = max(-1.0, min(1.0, ($covSum / ($n - 1)) / ($marginStd * $expStd)));
        }

        return [
            'marginMean' => $marginMean, 'marginStd' => $marginStd,
            'expMean'    => $expMean,    'expStd'    => $expStd,
            'adminMean'  => $adminMean,  'adminStd'  => $adminStd,
            'corrCoef'   => round($corrCoef, 4),
        ];
    }

    /** Kembalikan [mean, stddev] — stddev = 0 jika n < 2 */
    private function meanStd(array $arr): array
    {
        $n = count($arr);
        if ($n === 0) return [0.0, 0.0];
        $mean = array_sum($arr) / $n;
        if ($n === 1) return [$mean, 0.0];
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $arr)) / ($n - 1);
        return [$mean, sqrt($variance)];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PREDIKSI ENGINE v4.2 — WMA Adaptif + Holt DES
    //
    // Semua proyeksi berbasis MARGIN BERSIH (penjualan − HPP).
    // Faktor konservatif adaptif: basis 0.88 ± adj tren (±0.10 max).
    // Pengeluaran ops TIDAK dikali conserv (biaya cenderung tetap/naik).
    // ═══════════════════════════════════════════════════════════════════════

    private function buildPrediction(
        Period $period, int $daysInMonth,
        array $salesByDay, array $marginByDay, array $dayTotals,
        array $depositsByDay, array $transfersByDay,
        int $totalDeposits, int $totalAdminFees, int $totalTransferred, int $totalSurplus,
        int $totalExpense, int $totalIncome, int $totalMargin,
        int $openingCash, int $openingPenampung,
        int $netKas, int $finalBankBal, int $netTotal,
        int $totalTabungAktual, float $avgTabungPerHari,
        int $piutangBelumBayar, int $piutangMarginBelumBayar,
    ): array {
        // ── Konteks waktu ────────────────────────────────────────────────────
        $now        = now();
        $periodStart = Carbon::parse($period->start_date);
        $periodEnd   = Carbon::create($periodStart->year, $periodStart->month, $daysInMonth)->endOfDay();

        if ($period->status === 'closed' || $now->gt($periodEnd)) {
            $today = $daysInMonth; $remainDays = 0;
        } elseif ($periodStart->year === (int)$now->format('Y')
            && $periodStart->month === (int)$now->format('m')) {
            $today = $now->day; $remainDays = $daysInMonth - $today;
        } else {
            $today = 0; $remainDays = $daysInMonth;
        }
        $progressPct = $daysInMonth > 0 ? round($today / $daysInMonth * 100) : 0;

        // ── Hari aktif ───────────────────────────────────────────────────────
        $activeDays = fn(callable $cond) => max(1,
            collect(range(1, max(1, $today)))->filter($cond)->count()
        );
        $marginActiveDays = $activeDays(fn($d) => ($marginByDay[$d]['margin'] ?? 0) > 0);
        $expActiveDays    = $activeDays(fn($d) => ($dayTotals[$d] ?? 0) > 0);
        $depActiveDays    = $activeDays(fn($d) => ($depositsByDay[$d]['total'] ?? 0) > 0);
        $tfActiveDays     = $activeDays(fn($d) => ($transfersByDay[$d]['total'] ?? 0) > 0);

        // ── WMA 5 hari (bobot 2:1) + blended 60/40 dengan rata simpel ────────
        $window = fn(int $from, int $to) => collect(range(max(1, $from), max(1, $to)));

        $last5 = $window($today - 4, $today);
        $prev5 = $window($today - 9, $today - 5);
        $last7 = $window($today - 6, $today);
        $prev7 = $window($today - 13, $today - 7);

        $avgOf = fn(Collection $days, callable $val) => $days->avg($val) ?: 0.0;

        $avgMarginLast5 = $avgOf($last5, fn($d) => $marginByDay[$d]['margin'] ?? 0);
        $avgMarginPrev5 = $avgOf($prev5, fn($d) => $marginByDay[$d]['margin'] ?? 0) ?: $avgMarginLast5;
        $avgExpLast5    = $avgOf($last5, fn($d) => $dayTotals[$d] ?? 0);
        $avgExpPrev5    = $avgOf($prev5, fn($d) => $dayTotals[$d] ?? 0) ?: $avgExpLast5;

        $adminRateSimple  = $totalAdminFees / $depActiveDays;
        $marginRateWma    = ($avgMarginLast5 * 2 + $avgMarginPrev5) / 3;
        $marginRateSimple = $marginActiveDays > 0 ? $totalMargin / $marginActiveDays : 0;
        $expRateWma       = ($avgExpLast5 * 2 + $avgExpPrev5) / 3;
        $expRateSimple    = $expActiveDays > 0 ? $totalExpense / $expActiveDays : 0;

        // Blended 60% WMA + 40% simpel
        $marginRate = $marginRateWma * 0.6 + $marginRateSimple * 0.4;
        $expRate    = $expRateWma    * 0.6 + $expRateSimple    * 0.4;

        // ── Momentum 7 hari ──────────────────────────────────────────────────
        $avgMarginLast7 = $avgOf($last7, fn($d) => $marginByDay[$d]['margin'] ?? 0);
        $avgMarginPrev7 = $avgOf($prev7, fn($d) => $marginByDay[$d]['margin'] ?? 0) ?: $avgMarginLast7;
        $avgExpLast7    = $avgOf($last7, fn($d) => $dayTotals[$d] ?? 0);
        $avgExpPrev7    = $avgOf($prev7, fn($d) => $dayTotals[$d] ?? 0) ?: $avgExpLast7;

        $salesMomentum = $avgMarginPrev7 > 0
            ? round(($avgMarginLast7 - $avgMarginPrev7) / $avgMarginPrev7 * 100, 1) : 0;
        $expMomentum   = $avgExpPrev7 > 0
            ? round(($avgExpLast7 - $avgExpPrev7) / $avgExpPrev7 * 100, 1) : 0;

        // ── Faktor konservatif adaptif ────────────────────────────────────────
        $conserv = round(0.88 + min(0.10, max(-0.10, $salesMomentum / 150)), 4);

        // ── Gaji kurir ────────────────────────────────────────────────────────
        $gajiAktualSdIni = $totalTabungAktual * self::GAJI_PER_TABUNG;
        $predTabungSisa  = (int) round($avgTabungPerHari * $remainDays);
        $predGajiSisa    = $predTabungSisa * self::GAJI_PER_TABUNG;
        $predGajiTotal   = $gajiAktualSdIni + $predGajiSisa;
        $gajiOptim       = ($totalTabungAktual + (int)round($avgTabungPerHari * 1.05 * $remainDays)) * self::GAJI_PER_TABUNG;
        $gajiPesim       = ($totalTabungAktual + (int)round($avgTabungPerHari * 0.90 * $remainDays)) * self::GAJI_PER_TABUNG;

        // ── Piutang ──────────────────────────────────────────────────────────
        $piutangCairEstimasi = (int) round($piutangMarginBelumBayar * self::PIUTANG_CAIR_RATE);

        // ── Proyeksi komponen ─────────────────────────────────────────────────
        $projMargin = (int) round($marginRate * $conserv * $remainDays);
        $projExp    = (int) round($expRate * $remainDays);
        $projAdmin  = (int) round($adminRateSimple * $remainDays);

        // Optimis: margin +10%, expense -5%
        $projMarginOptim = (int) round($marginRate * min($conserv + 0.10, 1.05) * $remainDays);
        $projExpOptim    = (int) round($expRate * 0.95 * $remainDays);
        // Pesimis: margin -20%, expense +10%
        $projMarginPesim = (int) round($marginRate * max($conserv - 0.20, 0.60) * $remainDays);
        $projExpPesim    = (int) round($expRate * 1.10 * $remainDays);

        // ── Prediksi saldo KAS ────────────────────────────────────────────────
        // KAS kini + proyeksi margin + piutang cair − ops − admin
        // (Deposit ke penampung sudah tercermin dalam $netKas)
        $predKas = $netKas + $projMargin + $piutangCairEstimasi - $projExp - $projAdmin;

        return array_merge(compact(
            // Konteks waktu
            'today', 'remainDays', 'progressPct',
            // Rate harian
            'marginRate', 'marginRateWma', 'marginRateSimple',
            'expRate', 'expRateWma', 'expRateSimple',
            'adminRateSimple',
            // Momentum & faktor
            'salesMomentum', 'expMomentum', 'conserv',
            // Proyeksi komponen
            'projMargin', 'projExp', 'projAdmin',
            'projMarginOptim', 'projExpOptim',
            'projMarginPesim', 'projExpPesim',
            // Gaji
            'gajiAktualSdIni', 'predTabungSisa', 'predGajiSisa', 'predGajiTotal',
            'gajiOptim', 'gajiPesim',
            // Piutang
            'piutangBelumBayar', 'piutangMarginBelumBayar', 'piutangCairEstimasi',
            // Prediksi saldo
            'predKas'
        ), $this->calcScenarios(
            $netKas, $projMargin, $projMarginOptim, $projMarginPesim,
            $projExp, $projExpOptim, $projExpPesim,
            $projAdmin, $piutangCairEstimasi, $predGajiTotal
        ), $this->calcPredRatios(
            $totalMargin, $totalExpense, $projMargin, $projExp, $piutangCairEstimasi, $today
        ), $this->calcPredBank(
            $finalBankBal, $totalDeposits, $totalTransferred, $totalSurplus,
            $depActiveDays, $tfActiveDays, $remainDays
        ), $this->calcConfidence(
            $today, $salesMomentum, $expMomentum, $piutangBelumBayar, $totalIncome, $remainDays
        ), $this->calcHoltsDES(
            $today, $remainDays, $daysInMonth, $marginByDay, $dayTotals, $depositsByDay,
            $marginRateSimple, $expRateSimple, $adminRateSimple,
            $netKas, $piutangCairEstimasi, $predGajiTotal
        ));
    }

    /** Prediksi saldo per skenario */
    private function calcScenarios(
        int $netKas,
        int $projMargin, int $projMarginOptim, int $projMarginPesim,
        int $projExp, int $projExpOptim, int $projExpPesim,
        int $projAdmin, int $piutangCairEstimasi, int $predGajiTotal
    ): array {
        $base  = $netKas + $projMargin + $piutangCairEstimasi - $projExp - $projAdmin;
        $optim = $netKas + $projMarginOptim - $projExpOptim - $projAdmin;
        $pesim = $netKas + $projMarginPesim - $projExpPesim - $projAdmin;

        return [
            'predKas'            => (int) round($base),
            'predKasSetelahGaji' => (int) round($base - $predGajiTotal),
            'predKasOptim'       => (int) round($optim),
            'predKasPesim'       => (int) round($pesim),
        ];
    }

    /** Rasio biaya aktual dan prediksi akhir bulan */
    private function calcPredRatios(
        int $totalMargin, int $totalExpense,
        int $projMargin, int $projExp, int $piutangCairEstimasi,
        int $today
    ): array {
        $rasioAktual = $totalMargin > 0 ? round($totalExpense / $totalMargin * 100, 1) : 0.0;

        $fullMargin = $totalMargin + $projMargin + $piutangCairEstimasi;
        $fullExp    = $totalExpense + $projExp;
        $predRasio  = $fullMargin > 0 ? round($fullExp / $fullMargin * 100, 1) : 0.0;

        return compact('rasioAktual', 'predRasio');
    }

    /** Prediksi saldo BANK penampung */
    private function calcPredBank(
        int $finalBankBal, int $totalDeposits, int $totalTransferred, int $totalSurplus,
        int $depActiveDays, int $tfActiveDays, int $remainDays
    ): array {
        $grossDepRate = $depActiveDays > 0 ? $totalDeposits / $depActiveDays : 0;
        $grossTfRate  = $tfActiveDays  > 0 ? $totalTransferred / $tfActiveDays : 0;
        $netBankRate  = $grossDepRate - $grossTfRate;

        return ['predBank' => $finalBankBal + (int) round($netBankRate * $remainDays)];
    }

    /** Skor confidence (25–95%) */
    private function calcConfidence(
        int $today, float $salesMomentum, float $expMomentum,
        int $piutangBelumBayar, int $totalIncome, int $remainDays
    ): array {
        $c = 90;
        if ($today < 5)                                  $c -= 40;
        elseif ($today < 10)                             $c -= 20;
        elseif ($today < 15)                             $c -= 10;
        if (abs($salesMomentum) > 30)                    $c -= 15;
        elseif (abs($salesMomentum) > 20)                $c -= 8;
        if (abs($expMomentum) > 30)                      $c -= 10;
        if ($piutangBelumBayar > $totalIncome * 0.3)     $c -= 8;
        if ($remainDays === 0)                           $c  = 100;

        return ['confidence' => max(25, min(95, $c))];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HOLT'S DOUBLE EXPONENTIAL SMOOTHING (DES)
    //
    // α & β dioptimasi via grid-search SSE minimum (25 kombinasi).
    // Proyeksi per hari: ŷ(t+h) = L + h·T, dikumulasikan sisa bulan.
    // Interval skenario: ±1.5σ·√n (standard error proyeksi).
    // ═══════════════════════════════════════════════════════════════════════

    private function calcHoltsDES(
        int $today, int $remainDays, int $daysInMonth,
        array $marginByDay, array $dayTotals, array $depositsByDay,
        float $marginRateSimple, float $expRateSimple, float $adminRateSimple,
        int $netKas, int $piutangCairEstimasi, int $predGajiTotal
    ): array {
        // Susun time-series harian (hanya hari dengan aktivitas)
        $marginSeries = $expSeries = $adminSeries = [];
        for ($d = 1; $d <= $today; $d++) {
            $m = $marginByDay[$d]['margin'] ?? 0;
            $e = $dayTotals[$d] ?? 0;
            if ($m > 0 || $e > 0) {
                $marginSeries[] = (float) $m;
                $expSeries[]    = (float) $e;
                $adminSeries[]  = (float)($depositsByDay[$d]['admin'] ?? 0);
            }
        }
        $n = count($marginSeries);

        // Grid search atau fallback
        $alphas = [0.2, 0.3, 0.4, 0.5, 0.6];
        $betas  = [0.1, 0.2, 0.3, 0.4, 0.5];

        if ($n >= 3) {
            $bestM = $bestE = ['sse' => PHP_FLOAT_MAX, 'l' => 0, 't' => 0, 'alpha' => 0.4, 'beta' => 0.2];
            foreach ($alphas as $a) {
                foreach ($betas as $b) {
                    $rm = $this->runDES($marginSeries, $a, $b);
                    $re = $this->runDES($expSeries, $a, $b);
                    if ($rm['sse'] < $bestM['sse']) $bestM = $rm;
                    if ($re['sse'] < $bestE['sse']) $bestE = $re;
                }
            }
        } elseif ($n >= 2) {
            $bestM = $this->runDES($marginSeries, 0.4, 0.2);
            $bestE = $this->runDES($expSeries,    0.4, 0.2);
        } else {
            $bestM = ['l' => $marginRateSimple, 't' => 0.0, 'alpha' => 0.4, 'beta' => 0.2, 'sse' => 0];
            $bestE = ['l' => $expRateSimple,    't' => 0.0, 'alpha' => 0.4, 'beta' => 0.2, 'sse' => 0];
        }

        $bestAdmin = $n >= 2
            ? $this->runDES($adminSeries, 0.4, 0.2)
            : ['l' => $adminRateSimple, 't' => 0.0, 'alpha' => 0.4, 'beta' => 0.2, 'sse' => 0];

        // Proyeksi kumulatif: Σ(L + h·T), floor ke 0
        $projMargin = $projExp = $projAdmin = 0.0;
        for ($h = 1; $h <= $remainDays; $h++) {
            $projMargin += max(0.0, $bestM['l'] + $h * $bestM['t']);
            $projExp    += max(0.0, $bestE['l'] + $h * $bestE['t']);
            $projAdmin  += max(0.0, $bestAdmin['l'] + $h * $bestAdmin['t']);
        }
        $projMargin = (int) round($projMargin);
        $projExp    = (int) round($projExp);
        $projAdmin  = (int) round($projAdmin);

        // Prediksi saldo dasar
        $predKas = $netKas + $projMargin + $piutangCairEstimasi
            - $projExp - $projAdmin - $predGajiTotal;

        // Std dev untuk interval skenario
        $stdMargin = $n > 1 ? $this->stdDev($marginSeries) : abs($bestM['l']) * 0.15;
        $stdExp    = $n > 1 ? $this->stdDev($expSeries)    : abs($bestE['l'])  * 0.15;

        // SE proyeksi = std × √remainDays (propagasi ketidakpastian)
        $seMargin = $stdMargin * sqrt(max(1, $remainDays));
        $seExp    = $stdExp    * sqrt(max(1, $remainDays));

        $predOptim = (int) round($netKas + ($projMargin + $seMargin * 1.5)
            + $piutangCairEstimasi - max(0, $projExp - $seExp) - $projAdmin - $predGajiTotal);
        $predPesim = (int) round($netKas + max(0, $projMargin - $seMargin * 1.5)
            + $piutangCairEstimasi - ($projExp + $seExp) - $projAdmin - $predGajiTotal);

        // MAE in-sample
        [$maeMargin, $maeExp] = $this->calcMAE($marginSeries, $expSeries, $bestM, $bestE);

        // Confidence Holt DES
        $conf = min(92, max(20, (int) round(
            min($n, 20) / 20 * 40
            + ($maeMargin < $bestM['l'] * 0.2 ? 25 : 10)
            + ($maeExp    < $bestE['l']  * 0.3 ? 20 : 8)
            + ($n >= 10 ? 10 : 0)
        )));

        return [
            // Koefisien margin
            'holtsAlphaMargin'  => $bestM['alpha'],
            'holtsBetaMargin'   => $bestM['beta'],
            'holtsLevelMargin'  => $bestM['l'],
            'holtsTrendMargin'  => $bestM['t'],
            // Koefisien expense
            'holtsAlphaExp'     => $bestE['alpha'],
            'holtsBetaExp'      => $bestE['beta'],
            'holtsLevelExp'     => $bestE['l'],
            'holtsTrendExp'     => $bestE['t'],
            // Admin
            'holtsLevelAdmin'   => $bestAdmin['l'],
            'holtsTrendAdmin'   => $bestAdmin['t'],
            // Proyeksi
            'holtsProjMargin'   => $projMargin,
            'holtsProjExp'      => $projExp,
            'holtsProjAdmin'    => $projAdmin,
            // Prediksi saldo
            'holtsPredKas'      => (int) round($predKas),
            'holtsPredKasOptim' => $predOptim,
            'holtsPredKasPesim' => $predPesim,
            // Diagnostik
            'holtsMarginTrend'  => $bestM['t'] > 500 ? '📈 Naik' : ($bestM['t'] < -500 ? '📉 Turun' : '➡ Stabil'),
            'holtsExpTrend'     => $bestE['t'] > 100 ? '📈 Naik' : ($bestE['t'] < -100 ? '📉 Turun' : '➡ Stabil'),
            'holtsMaeMargin'    => $maeMargin,
            'holtsMaeExp'       => $maeExp,
            'holtsDataPoints'   => $n,
            'holtsConfidence'   => $conf,
            'holtsSEMargin'     => (int) round($seMargin),
            'holtsSEExp'        => (int) round($seExp),
        ];
    }

    /**
     * Jalankan DES satu pass — kembalikan level L, trend T, dan SSE
     * L(0) = y[0],  T(0) = y[1] − y[0]
     */
    private function runDES(array $series, float $alpha, float $beta): array
    {
        $n = count($series);
        if ($n < 2) {
            return ['l' => $series[0] ?? 0.0, 't' => 0.0, 'alpha' => $alpha, 'beta' => $beta, 'sse' => 0.0];
        }

        $L = $series[0];
        $T = $series[1] - $series[0];
        $sse = 0.0;

        for ($i = 1; $i < $n; $i++) {
            $yHat = $L + $T;
            $sse += ($series[$i] - $yHat) ** 2;
            $Lprev = $L;
            $L = $alpha * $series[$i] + (1 - $alpha) * ($L + $T);
            $T = $beta  * ($L - $Lprev) + (1 - $beta) * $T;
        }

        return compact('L', 'T', 'alpha', 'beta', 'sse') + ['l' => $L, 't' => $T];
    }

    /** MAE in-sample untuk dua series sekaligus */
    private function calcMAE(array $marginSeries, array $expSeries, array $paramM, array $paramE): array
    {
        $n = count($marginSeries);
        if ($n < 2) return [0, 0];

        $Lm = $marginSeries[0]; $Tm = $marginSeries[1] - $marginSeries[0];
        $Le = $expSeries[0];    $Te = $expSeries[1]    - $expSeries[0];
        $sumM = $sumE = 0.0;

        for ($i = 1; $i < $n; $i++) {
            $sumM += abs($marginSeries[$i] - ($Lm + $Tm));
            $sumE += abs($expSeries[$i]    - ($Le + $Te));
            $LmPrev = $Lm; $LePrev = $Le;
            $Lm = $paramM['alpha'] * $marginSeries[$i] + (1 - $paramM['alpha']) * ($Lm + $Tm);
            $Tm = $paramM['beta']  * ($Lm - $LmPrev)   + (1 - $paramM['beta'])  * $Tm;
            $Le = $paramE['alpha'] * $expSeries[$i]    + (1 - $paramE['alpha']) * ($Le + $Te);
            $Te = $paramE['beta']  * ($Le - $LePrev)   + (1 - $paramE['beta'])  * $Te;
        }

        return [(int) round($sumM / ($n - 1)), (int) round($sumE / ($n - 1))];
    }

    /** Std dev populasi (sample, n−1) */
    private function stdDev(array $arr): float
    {
        $n = count($arr);
        if ($n < 2) return 0.0;
        $mean = array_sum($arr) / $n;
        return sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $arr)) / ($n - 1));
    }
}