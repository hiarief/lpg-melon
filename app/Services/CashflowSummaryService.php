<?php
// app/Services/CashflowSummaryService.php
namespace App\Services;

use App\Models\Period;
use App\Models\DailyExpense;
use App\Models\Distribution;
use App\Models\CourierDeposit;
use App\Models\AccountTransfer;

class CashflowSummaryService
{
    const HPP_PER_TABUNG = 16_000;
    const GAJI_PER_TABUNG = 500;
    const PIUTANG_CAIR_RATE = 0.30;   // 30% piutang diasumsikan cair

    public function forPeriod(Period $period): array
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);

        $categoryTotals = array_fill_keys(array_keys(DailyExpense::$categoryLabels), 0);
        $dayTotals      = array_fill(1, $daysInMonth, 0);

        foreach (DailyExpense::where('period_id', $period->id)->get() as $e) {
            $day = $e->expense_date->day;
            $categoryTotals[$e->category] += $e->amount;
            $dayTotals[$day]              += $e->amount;
        }

        $salesByDay = Distribution::where('period_id', $period->id)
            ->selectRaw('DAY(dist_date) as day, SUM(paid_amount) as total')
            ->groupBy('day')->pluck('total', 'day')->toArray();

        $marginByDay = Distribution::where('period_id', $period->id)
            ->selectRaw('DAY(dist_date) as day, SUM((qty * price_per_unit) - (qty * ?)) as margin', [self::HPP_PER_TABUNG])
            ->groupBy('day')->pluck('margin', 'day')->toArray();

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

        $totalExpense     = (int) array_sum($categoryTotals);
        $totalIncome      = (int) array_sum($salesByDay);
        $totalMargin      = (int) array_sum($marginByDay);
        $totalDeposits    = (int) array_sum(array_column($depositsByDay, 'total'));
        $totalAdminFees   = (int) array_sum(array_column($depositsByDay, 'admin'));
        $totalTransferred = (int) array_sum(array_column($transfersByDay, 'total'));

        $totalSurplus     = (int) array_sum(array_column($transfersByDay, 'surplus'));

        $openingCash      = (int) $period->opening_cash;
        $openingPenampung = (int) $period->opening_penampung;

        [$dailyBalance, $dailyBankBalance] = $this->calcDailyBalances(
            $daysInMonth, $salesByDay, $dayTotals,
            $depositsByDay, $transfersByDay,
            $openingCash, $openingPenampung
        );

        $netKas       = $dailyBalance[$daysInMonth];
        $finalBankBal = $dailyBankBalance[$daysInMonth];
        $netTotal     = $netKas + $finalBankBal;

        $activeDays = collect($dayTotals + $salesByDay)->filter()->count(); // sesuaikan dgn definisi "hari aktif" versi kamu

        $rasioOperasional = $totalMargin > 0 ? round(($totalExpense / $totalMargin) * 100, 1) : 0;
        $rasioGross = $netKas + $totalExpense + $totalDeposits + $totalAdminFees > 0
            ? round((($totalExpense + $totalDeposits + $totalAdminFees) / ($openingCash + $totalIncome)) * 100, 1)
            : 0;

        return [
            'period_id'        => $period->id,
            'label'            => $period->label,
            'openingCash'      => $openingCash,
            'openingPenampung' => $openingPenampung,
            'totalIncome'      => $totalIncome,
            'totalExpense'     => $totalExpense,
            'totalMargin'      => $totalMargin,
            'totalDeposits'    => $totalDeposits,
            'totalAdminFees'   => $totalAdminFees,
            'totalTransferred' => $totalTransferred,
            'totalSurplus'     => $totalSurplus,
            'finalBankBal'            => $dailyBankBalance[$daysInMonth], // ← salah, harusnya $dailyBalance
            'netKas'      => $dailyBalance[$daysInMonth],      // ← salah, harusnya $dailyBankBalance
            'netTotal'         => $netTotal,
            'rasioOperasional' => $rasioOperasional,
            'rasioGross'       => $rasioGross,
        ];
    }

    // pindahkan method private calcDailyBalances() dari CashflowController ke sini
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
}
