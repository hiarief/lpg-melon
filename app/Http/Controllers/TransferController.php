<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Models\Courier;
use App\Models\CourierDeposit;
use App\Models\DeliveryOrder;
use App\Models\Period;
use App\Models\Saving;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    // ────────────────────────────────────────────────────────────────
    //  PUBLIC ACTIONS
    // ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $period  = Period::findOrFail($request->period_id ?? Period::current()?->id);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        $couriers  = Courier::where('is_active', true)->get();
        $deposits  = CourierDeposit::with('courier')
            ->where('period_id', $period->id)
            ->orderBy('deposit_date')
            ->get();
        $transfers = AccountTransfer::with('deliveryOrders.outlet')
            ->where('period_id', $period->id)
            ->orderBy('transfer_date')
            ->get();

        // Unpaid DOs (period ini + sebelumnya)
        [$unpaidDOs, $prevUnpaidDOs, $allUnpaidDOs] = $this->resolveUnpaidDOs($period);

        // Agregat utama
        $totals = $this->calculateTotals($period, $deposits, $transfers);

        // Data untuk chart
        $chartData = $this->buildChartData($deposits, $transfers, $period->opening_penampung, $period);

        // Indikator kesehatan
        $indicators = $this->buildIndicators($totals);

        // Mutasi / riwayat saldo
        $balanceRows = $this->buildBalanceRows($period->opening_penampung, $deposits, $transfers);

        $totalPiutangDO = $allUnpaidDOs->sum(fn($d) => $d->remainingAmount());
        return view('transfer.index', array_merge(
            compact('period', 'periods', 'couriers', 'deposits', 'transfers','totalPiutangDO',
                    'unpaidDOs', 'prevUnpaidDOs', 'allUnpaidDOs',
                    'balanceRows', 'chartData', 'indicators'),
            $totals
        ));
    }

    public function storeDeposit(Request $request)
    {
        $data = $request->validate([
            'period_id'    => 'required|exists:periods,id',
            'courier_id'   => 'required|exists:couriers,id',
            'deposit_date' => 'required|date',
            'amount'       => 'required|integer|min:1',
            'admin_fee'    => 'nullable|integer|min:0',
            'reference_no' => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        $period = Period::findOrFail($data['period_id']);
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        CourierDeposit::create([
            ...$data,
            'admin_fee' => (int) ($data['admin_fee'] ?? 0),
        ]);

        return back()->with('success', 'Setoran kurir disimpan.');
    }

    public function storeTransfer(Request $request)
    {
        $data = $request->validate([
            'period_id'                    => 'required|exists:periods,id',
            'transfer_date'                => 'required|date',
            'amount'                       => 'required|integer|min:1',
            'notes'                        => 'nullable|string',
            'alloc_mode'                   => 'required|in:auto,manual',
            'do_allocations'               => 'nullable|array',
            'do_allocations.*.do_id'       => 'required_if:alloc_mode,manual|exists:delivery_orders,id',
            'do_allocations.*.amount'      => 'required_if:alloc_mode,manual|integer|min:1',
        ]);

        $period = Period::findOrFail($data['period_id']);
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        DB::transaction(function () use ($data) {
            $transfer = AccountTransfer::create([
                'period_id'        => $data['period_id'],
                'transfer_date'    => $data['transfer_date'],
                'amount'           => $data['amount'],
                'do_equivalent_qty'=> 0,
                'surplus'          => 0,
                'notes'            => $data['notes'] ?? null,
            ]);

            [$doEquivQty, $surplus] = $data['alloc_mode'] === 'auto'
                ? $this->allocateAuto($transfer, $data['amount'], (int) $data['period_id'])
                : $this->allocateManual($transfer, $data['amount'], $data['do_allocations'] ?? []);

            $transfer->update(['do_equivalent_qty' => $doEquivQty, 'surplus' => $surplus]);

            if ($surplus > 0) {
                Saving::create([
                    'period_id'           => $data['period_id'],
                    'account_transfer_id' => $transfer->id,
                    'entry_date'          => $data['transfer_date'],
                    'type'                => 'in',
                    'amount'              => $surplus,
                    'description'         => 'Surplus transfer ' . $data['transfer_date']
                                            . ($data['notes'] ? ' — ' . $data['notes'] : ''),
                ]);
            }
        });

        return back()->with('success', 'Transfer disimpan & status DO otomatis diperbarui.');
    }

    public function destroyDeposit(CourierDeposit $deposit)
    {
        $periodId = $deposit->period_id;
        $deposit->delete();

        return redirect()->route('transfer.index', ['period_id' => $periodId])
            ->with('success', 'Setoran dihapus.');
    }

    public function destroyTransfer(AccountTransfer $transfer)
    {
        $periodId = $transfer->period_id;

        DB::transaction(function () use ($transfer) {
            $doIds = $transfer->deliveryOrders()->pluck('delivery_orders.id');
            $transfer->deliveryOrders()->detach();
            Saving::where('account_transfer_id', $transfer->id)->delete();
            $transfer->delete();

            DeliveryOrder::whereIn('id', $doIds)->get()->each->recalcPayment();
        });

        return redirect()->route('transfer.index', ['period_id' => $periodId])
            ->with('success', 'Transfer dihapus & status DO dikembalikan.');
    }

    // ────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS — ALOKASI
    // ────────────────────────────────────────────────────────────────

    /** Alokasi otomatis: lunasi DO terlama dulu; sisa menjadi surplus. */
    private function allocateAuto(AccountTransfer $transfer, int $amount, int $periodId): array
    {
        $remaining = $amount;
        $doEquivQty = 0;

        $doList = DeliveryOrder::with('outlet')
            ->whereHas('period', fn($q) => $q->where('id', '<=', $periodId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderByRaw("CASE WHEN notes LIKE '%Carry-over%' THEN 0 ELSE 1 END")
            ->orderBy('do_date')
            ->orderBy('id')
            ->get();

        foreach ($doList as $do) {
            if ($remaining <= 0) break;

            $allocAmount = min($remaining, $do->remainingAmount());
            if ($allocAmount <= 0) continue;

            $transfer->deliveryOrders()->attach($do->id, ['amount_allocated' => $allocAmount]);
            $remaining  -= $allocAmount;
            $doEquivQty += intdiv($allocAmount, $do->price_per_unit);
            $do->recalcPayment();
        }

        return [$doEquivQty, $remaining]; // sisa = surplus
    }

    /** Alokasi manual per-DO sesuai input pengguna. */
    private function allocateManual(AccountTransfer $transfer, int $amount, array $allocations): array
    {
        $remaining  = $amount;
        $doEquivQty = 0;

        foreach ($allocations as $alloc) {
            $do = DeliveryOrder::find($alloc['do_id']);
            if (! $do) continue;

            $allocAmount = min((int) $alloc['amount'], $do->remainingAmount());
            if ($allocAmount <= 0) continue;

            $transfer->deliveryOrders()->attach($do->id, ['amount_allocated' => $allocAmount]);
            $remaining  -= $allocAmount;
            $doEquivQty += intdiv($allocAmount, $do->price_per_unit);
            $do->recalcPayment();
        }

        return [$doEquivQty, max(0, $remaining)];
    }

    // ────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS — KALKULASI & TRANSFORMASI DATA
    // ────────────────────────────────────────────────────────────────

    /**
     * Kembalikan [unpaidDOs, prevUnpaidDOs, allUnpaidDOs].
     * allUnpaidDOs sudah diurutkan: prev period → carry-over → bulan ini.
     */
    private function resolveUnpaidDOs(Period $period): array
    {
        $unpaidDOs = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderByRaw("CASE WHEN notes LIKE '%Carry-over%' THEN 0 ELSE 1 END")
            ->orderBy('do_date')
            ->get();

        $prevUnpaidDOs = DeliveryOrder::with(['outlet', 'period'])
            ->whereHas('period', fn($q) => $q->where('id', '<', $period->id))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('do_date')
            ->get();

        $allUnpaidDOs = $prevUnpaidDOs
            ->concat($unpaidDOs->filter(fn($d) => str_contains($d->notes ?? '', 'Carry-over')))
            ->concat($unpaidDOs->filter(fn($d) => ! str_contains($d->notes ?? '', 'Carry-over')));

        return [$unpaidDOs, $prevUnpaidDOs, $allUnpaidDOs];
    }

    /**
     * Hitung semua angka agregat; kembalikan sebagai array asosiatif
     * agar bisa di-merge langsung ke compact().
     */
    private function calculateTotals(Period $period, Collection $deposits, Collection $transfers): array
    {
        $totalDeposited     = $deposits->sum('amount');
        $totalAdmin         = $deposits->sum('admin_fee');
        $totalNominal       = $deposits->sum(fn($d) => $d->amount + $d->admin_fee);
        $totalBersih        = $totalDeposited;
        $totalTransferred   = $transfers->sum('amount');
        $totalTransferQty   = $transfers->sum('do_equivalent_qty');
        $totalSurplus       = $transfers->sum('surplus');
        $penampungNow       = $period->opening_penampung + $totalDeposited - $totalTransferred;

        // Utilisasi: seberapa besar dari penampung sudah tersalurkan
        $utilisasiBar = ($period->opening_penampung + $totalDeposited) > 0
            ? min(round(($totalTransferred + $totalAdmin) / ($period->opening_penampung + $totalDeposited) * 100), 100)
            : 0;

        return compact(
            'totalDeposited', 'totalAdmin', 'totalNominal', 'totalBersih',
            'totalTransferred', 'totalTransferQty', 'totalSurplus',
            'penampungNow', 'utilisasiBar'
        );
    }

    /**
     * Bangun data tren harian dan komposisi per kurir untuk chart.
     * Mengembalikan satu array $chartData yang siap di-json_encode di Blade.
     */
    private function buildChartData(Collection $deposits, Collection $transfers, int $openingBalance, Period $period): array
    {
        $daysInMonth = Carbon::create($period->year, $period->month)->daysInMonth;

        // Indeks per hari
        $depByDay = [];
        foreach ($deposits as $d) {
            $day = $d->deposit_date->day;
            $depByDay[$day] = ($depByDay[$day] ?? 0) + $d->amount;
        }

        $tfByDay = [];
        foreach ($transfers as $t) {
            $day = $t->transfer_date->day;
            $tfByDay[$day] = ($tfByDay[$day] ?? 0) + $t->amount;
        }

        // Hanya hari yang ada aktivitas
        $trendLabels = $trendDep = $trendTf = $trendSaldo = [];
        $saldoRun = $openingBalance;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dep = $depByDay[$d] ?? 0;
            $tf  = $tfByDay[$d]  ?? 0;
            if ($dep > 0 || $tf > 0) {
                $saldoRun      += $dep - $tf;
                $trendLabels[]  = $d;
                $trendDep[]     = $dep;
                $trendTf[]      = $tf;
                $trendSaldo[]   = $saldoRun;
            }
        }

        // Komposisi per kurir
        $kurirMap = [];
        foreach ($deposits as $d) {
            $name = $d->courier->name;
            $kurirMap[$name] = ($kurirMap[$name] ?? 0) + $d->amount;
        }
        arsort($kurirMap);

        return [
            'trendLabels'  => $trendLabels,
            'trendDep'     => $trendDep,
            'trendTf'      => $trendTf,
            'trendSaldo'   => $trendSaldo,
            'kurirNames'   => array_keys($kurirMap),
            'kurirTotals'  => array_values($kurirMap),
        ];
    }

    /**
     * Bangun indikator kesehatan finansial.
     * Setiap item: [label, nilai (string), catatan, persentase bar (0–100), warna CSS].
     */
    private function buildIndicators(array $totals): array
    {
        $utilisasi  = $totals['totalDeposited'] > 0
            ? round($totals['totalTransferred'] / $totals['totalDeposited'] * 100)
            : 0;

        $rasioAdmin = $totals['totalDeposited'] > 0
            ? round($totals['totalAdmin'] / $totals['totalDeposited'] * 100, 2)
            : 0;

        // piutangPct harus dihitung dari allUnpaidDOs — kita simpan totalPiutangDO di totals bila ada,
        // tapi karena belum masuk sini, kita biarkan view memanggil $indicators['piutangPct'] dari luar.
        // Nilai ini akan disempurnakan via compact di index() setelah resolveUnpaidDOs.

        return compact('utilisasi', 'rasioAdmin');
    }

    /**
     * Hasilkan baris mutasi rekening penampung berurutan berdasarkan tanggal,
     * dengan kolom saldo berjalan.
     */
    private function buildBalanceRows(int $opening, Collection $deposits, Collection $transfers): array
    {
        $events = collect();

        foreach ($deposits as $d) {
            $events->push([
                'date'   => $d->deposit_date,
                'type'   => 'in',
                'amount' => $d->amount,
                'desc'   => 'Setoran Kurir (' . $d->courier->name . ')',
            ]);
        }

        foreach ($transfers as $t) {
            $events->push([
                'date'   => $t->transfer_date,
                'type'   => 'out',
                'amount' => $t->amount,
                'desc'   => 'Transfer ke Rek Utama',
            ]);
        }

        $runningBalance = $opening;
        $rows = [];

        foreach ($events->sortBy('date') as $event) {
            $runningBalance += $event['type'] === 'in' ? $event['amount'] : -$event['amount'];
            $rows[] = $event + ['balance' => $runningBalance];
        }

        return $rows;
    }
}