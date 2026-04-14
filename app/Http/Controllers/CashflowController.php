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

        // Margin bersih = paid_amount - (qty × HPP), hanya paid_amount > 0
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
            netKas: $dailyBalance[$daysInMonth],
            finalBankBal: $dailyBankBalance[$daysInMonth],
            totalTabungAktual: $totalTabungAktual,
            avgTabungPerHari: $avgTabungPerHari,
            piutangBelumBayar: $piutangBelumBayar,
            piutangMarginBelumBayar: $piutangMarginBelumBayar,
        );

        return view('cashflow.index', compact(
            'period','periods','expenses','categories',
            'expenseGrid','categoryTotals','dayTotals',
            'salesByDay','marginByDay','daysInMonth',
            'totalExpense','totalIncome','totalMargin',
            'depositsByDay','totalDeposits','totalAdminFees',
            'transfersByDay','totalTransferred',
            'openingCash','openingPenampung',
            'dailyBalance','dailyBankBalance',
            'totalTabungAktual','avgTabungPerHari',
            'piutangBelumBayar','piutangMarginBelumBayar',
            'pred'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PREDIKSI ENGINE v4.1
    //
    // Fix vs v4:
    // - predBank: pakai net rate (deposit - transfer), bukan gross deposit
    //   Penampung = transit, saldo tidak bergerak jauh dari posisi kini
    // - predRasio: vs totalMargin bukan totalIncome (gross)
    //   Rasio sehat = biaya ops / margin bersih, bukan vs omzet
    // - rasioAktual: tambahan, untuk display kondisi s/d hari ini
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
        $salesActiveDays  = collect(range(1, max(1, $today)))
            ->filter(fn($d) => ($salesByDay[$d] ?? 0) > 0)->count() ?: 1;
        $tfActiveDays     = collect(range(1, max(1, $today)))
            ->filter(fn($d) => ($transfersByDay[$d]['total'] ?? 0) > 0)->count() ?: 1;

        // ── WMA 5 hari ───────────────────────────────────────────────────────
        $last5 = collect(range(max(1, $today - 4), max(1, $today)));
        $prev5 = collect(range(max(1, $today - 9), max(1, $today - 5)));

        $avgMarginLast5 = $last5->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0);
        $avgMarginPrev5 = $prev5->count() > 0
            ? $prev5->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0) : $avgMarginLast5;
        $avgExpLast5    = $last5->avg(fn($d) => $dayTotals[$d] ?? 0);
        $avgExpPrev5    = $prev5->count() > 0
            ? $prev5->avg(fn($d) => $dayTotals[$d] ?? 0) : $avgExpLast5;
        $avgSalesLast5  = $last5->avg(fn($d) => $salesByDay[$d] ?? 0);
        $avgSalesPrev5  = $prev5->count() > 0
            ? $prev5->avg(fn($d) => $salesByDay[$d] ?? 0) : $avgSalesLast5;

        $marginRateWma  = ($avgMarginLast5 * 2 + $avgMarginPrev5) / 3;
        $expRateWma     = ($avgExpLast5 * 2 + $avgExpPrev5) / 3;
        $salesRateWma   = ($avgSalesLast5 * 2 + $avgSalesPrev5) / 3;

        $marginRateSimple = $totalMargin / $marginActiveDays;
        $expRateSimple    = $totalExpense / $expActiveDays;
        $salesRateSimple  = $totalIncome / $salesActiveDays;
        $adminRate        = $totalAdminFees / $depActiveDays;

        $marginRate = ($marginRateWma * 0.6) + ($marginRateSimple * 0.4);
        $expRate    = ($expRateWma * 0.6)    + ($expRateSimple * 0.4);
        $salesRate  = ($salesRateWma * 0.6)  + ($salesRateSimple * 0.4);
        $depRate    = 0; // deposit embedded di margin

        // ── Momentum 7 hari ──────────────────────────────────────────────────
        $last7 = collect(range(max(1, $today - 6), max(1, $today)));
        $prev7 = collect(range(max(1, $today - 13), max(1, $today - 7)));

        $avgMarginLast7 = $last7->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0);
        $avgMarginPrev7 = $prev7->count() > 0
            ? $prev7->avg(fn($d) => $marginByDay[$d]['margin'] ?? 0) : $avgMarginLast7;
        $avgExpLast7    = $last7->avg(fn($d) => $dayTotals[$d] ?? 0);
        $avgExpPrev7    = $prev7->count() > 0
            ? $prev7->avg(fn($d) => $dayTotals[$d] ?? 0) : $avgExpLast7;

        $salesMomentum = $avgMarginPrev7 > 0
            ? (($avgMarginLast7 - $avgMarginPrev7) / $avgMarginPrev7 * 100) : 0;
        $expMomentum   = $avgExpPrev7 > 0
            ? (($avgExpLast7 - $avgExpPrev7) / $avgExpPrev7 * 100) : 0;

        // ── Konservatif adaptif ───────────────────────────────────────────────
        $baseCons = 0.88;
        $trendAdj = min(0.08, max(-0.10, $salesMomentum / 150));
        $conserv  = $baseCons + $trendAdj;

        // ── Proyeksi ─────────────────────────────────────────────────────────
        $projSales  = round($marginRate * $conserv * $remainDays);
        $projExp    = round($expRate * $remainDays);
        $projAdmin  = round($adminRate * $conserv * $remainDays);
        $projDep    = 0;

        $piutangCairEstimasi = round($piutangMarginBelumBayar * 0.30);

        $projSalesOptim = round($marginRate * 1.05 * $remainDays);
        $projExpOptim   = round($expRate * 0.90 * $remainDays);
        $projSalesPesim = round($marginRate * 0.75 * $remainDays);
        $projExpPesim   = round($expRate * 1.15 * $remainDays);

        // ── Gaji (info saja, keluar tgl 1 bulan depan) ───────────────────────
        $gajiPerTabung   = 500;
        $gajiAktualSdIni = $totalTabungAktual * $gajiPerTabung;
        $predTabungSisa  = round($avgTabungPerHari * $remainDays);
        $predGajiSisa    = $predTabungSisa * $gajiPerTabung;
        $predGajiTotal   = $gajiAktualSdIni + $predGajiSisa;

        $gajiOptim = ($totalTabungAktual + round($avgTabungPerHari * 1.05 * $remainDays)) * $gajiPerTabung;
        $gajiPesim = ($totalTabungAktual + round($avgTabungPerHari * 0.85 * $remainDays)) * $gajiPerTabung;

        // ── Prediksi KAS ─────────────────────────────────────────────────────
        $predKas = $netKas + $projSales + $piutangCairEstimasi - $projExp - $projAdmin;
        $predKasSetelahGaji = $predKas - $predGajiTotal;
        $predKasOptim = $netKas + $projSalesOptim - $projExpOptim - round($adminRate * $remainDays);
        $predKasPesim = $netKas + $projSalesPesim - $projExpPesim - round($adminRate * $remainDays);

        // ── FIX: Prediksi BANK penampung ─────────────────────────────────────
        // Penampung = rekening TRANSIT
        // Deposit masuk dari kurir → hampir seluruhnya langsung TF ke agen
        // Saldo penampung bergerak sangat kecil dari posisi sekarang
        //
        // Net rate = deposit rate - transfer rate per hari
        // Kalau histori menunjukkan net ≈ 0, prediksi = saldo kini
        $grossDepRate    = $depActiveDays > 0 ? $totalDeposits / $depActiveDays : 0;
        $grossTfRate     = $tfActiveDays  > 0 ? $totalTransferred / $tfActiveDays : 0;
        $netBankRate     = $grossDepRate - $grossTfRate;

        // Prediksi bank = saldo kini + net perubahan sisa bulan
        // Karena net ≈ 0, predBank ≈ finalBankBal (wajar untuk rekening transit)
        $predBank = $finalBankBal + round($netBankRate * $conserv * $remainDays);

        // ── FIX: Rasio biaya vs MARGIN (bukan gross sales) ───────────────────
        // Rasio yang bermakna untuk bisnis ini:
        // = pengeluaran operasional / margin bersih
        // Misal: ops Rp 1.4jt / margin Rp 7jt = 20% → sehat
        // (dulu salah: ops Rp 1.4jt / gross Rp 17jt = 8% → terlalu optimis
        //  atau ops / totalAvailable = 66% → terlalu pesimis)
        //
        // Aktual s/d hari ini:
        $rasioAktual = $totalMargin > 0
            ? round($totalExpense / $totalMargin * 100, 1) : 0;

        // Proyeksi akhir bulan:
        $predTotalMarginFull = $totalMargin + $projSales + $piutangCairEstimasi;
        $predTotalExpFull    = $totalExpense + $projExp;
        $predRasio = $predTotalMarginFull > 0
            ? round($predTotalExpFull / $predTotalMarginFull * 100, 1) : 0;

        // ── Confidence ───────────────────────────────────────────────────────
        $confidence = 90;
        if ($today < 5)                      $confidence -= 40;
        elseif ($today < 10)                 $confidence -= 20;
        elseif ($today < 15)                 $confidence -= 10;
        if (abs($salesMomentum) > 30)        $confidence -= 15;
        elseif (abs($salesMomentum) > 20)    $confidence -= 8;
        if (abs($expMomentum) > 30)          $confidence -= 10;
        if ($piutangBelumBayar > $totalIncome * 0.3) $confidence -= 8;
        if ($remainDays === 0)               $confidence  = 100;
        $confidence = max(25, min(95, $confidence));

        return compact(
            'today','remainDays','progressPct',
            'salesRate','salesRateWma','salesRateSimple',
            'marginRate','marginRateWma','marginRateSimple',
            'expRate','adminRate','depRate',
            'salesMomentum','expMomentum',
            'conserv',
            'projSales','projExp','projDep','projAdmin',
            'projSalesOptim','projExpOptim',
            'projSalesPesim','projExpPesim',
            'gajiPerTabung','gajiAktualSdIni','predTabungSisa','predGajiSisa','predGajiTotal',
            'gajiOptim','gajiPesim',
            'piutangBelumBayar','piutangMarginBelumBayar','piutangCairEstimasi',
            'predKas','predKasSetelahGaji','predKasOptim','predKasPesim',
            'predBank','predRasio','rasioAktual',
            'confidence',
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
        return redirect()->route('cashflow.index', ['period_id' => $periodId])
            ->with('success', 'Pengeluaran dihapus.');
    }
}
