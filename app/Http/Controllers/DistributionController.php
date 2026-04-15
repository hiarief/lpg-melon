<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DistributionController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // PUBLIC ACTIONS
    // ──────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $period  = Period::findOrFail($request->period_id ?? Period::current()?->id);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        $distributions = Distribution::with(['customer', 'courier'])
            ->where('period_id', $period->id)
            ->orderBy('dist_date')
            ->orderBy('customer_id')
            ->get();

        $customers   = Customer::where('is_active', true)->orderBy('name')->get();
        $couriers    = Courier::where('is_active', true)->get();
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);
        $totalDoQty  = $this->getTotalDoQty($period);

        $grid           = $this->buildGrid($distributions);
        $customerTotals = $this->buildCustomerTotals($customers, $distributions);

        // Derived data for charts / summaries — computed once here, passed as variables
        $chartData      = $this->buildDailyChartData($grid, $daysInMonth);
        $summaryData    = $this->buildSummaryData($customerTotals, $chartData, $daysInMonth);
        $projectionData = $this->buildProjectionData($chartData['qty'], $chartData['labels'], $daysInMonth);

        return view('distributions.index', compact(
            'period', 'periods', 'distributions',
            'customers', 'couriers', 'daysInMonth',
            'grid', 'customerTotals', 'totalDoQty',
            'chartData', 'summaryData', 'projectionData',
        ));
    }

    public function create(Request $request)
    {
        $period    = Period::findOrFail($request->period_id ?? Period::current()?->id);
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $couriers  = Courier::where('is_active', true)->get();

        return view('distributions.create', compact('period', 'customers', 'couriers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_id'      => 'required|exists:periods,id',
            'courier_id'     => 'required|exists:couriers,id',
            'customer_id'    => 'required|exists:customers,id',
            'dist_date'      => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:0|max:25000',
            'payment_status' => 'required|in:paid,deferred,partial',
            'paid_amount'    => 'nullable|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        $this->guardClosedPeriod($data['period_id']);

        $data['paid_amount'] = $this->resolvePaidAmount(
            $data['payment_status'], $data['qty'], $data['price_per_unit'], $data['paid_amount'] ?? 0
        );

        Distribution::create($data);

        return redirect()
            ->route('distributions.index', ['period_id' => $data['period_id']])
            ->with('success', 'Distribusi berhasil disimpan.');
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'period_id'                => 'required|exists:periods,id',
            'courier_id'               => 'required|exists:couriers,id',
            'dist_date'                => 'required|date',
            'rows'                     => 'required|array|min:1',
            'rows.*.customer_id'       => 'required|exists:customers,id',
            'rows.*.qty'               => 'required|integer|min:1',
            'rows.*.price_per_unit'    => 'required|integer|min:10000',
            'rows.*.payment_status'    => 'required|in:paid,deferred,partial',
            'rows.*.paid_amount'       => 'nullable|integer|min:0',
        ]);

        $this->guardClosedPeriod($data['period_id']);

        foreach ($data['rows'] as $row) {
            Distribution::create([
                'period_id'      => $data['period_id'],
                'courier_id'     => $data['courier_id'],
                'customer_id'    => $row['customer_id'],
                'dist_date'      => $data['dist_date'],
                'qty'            => $row['qty'],
                'price_per_unit' => $row['price_per_unit'],
                'payment_status' => $row['payment_status'],
                'paid_amount'    => $this->resolvePaidAmount(
                    $row['payment_status'], $row['qty'], $row['price_per_unit'], $row['paid_amount'] ?? 0
                ),
                'notes'          => $row['notes'] ?? null,
            ]);
        }

        return redirect()
            ->route('distributions.index', ['period_id' => $data['period_id']])
            ->with('success', 'Distribusi harian berhasil disimpan.');
    }

    public function edit(Distribution $distribution)
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $couriers  = Courier::where('is_active', true)->get();

        return view('distributions.edit', compact('distribution', 'customers', 'couriers'));
    }

    public function update(Request $request, Distribution $distribution)
    {
        $data = $request->validate([
            'dist_date'      => 'required|date',
            'qty'            => 'required|integer|min:1',
            'price_per_unit' => 'required|integer|min:0',
            'payment_status' => 'required|in:paid,deferred,partial',
            'paid_amount'    => 'nullable|integer|min:0',
            'notes'          => 'nullable|string',
        ]);

        $data['paid_amount'] = $this->resolvePaidAmount(
            $data['payment_status'], $data['qty'], $data['price_per_unit'], $data['paid_amount'] ?? 0
        );

        $distribution->update($data);

        return redirect()
            ->route('distributions.index', ['period_id' => $distribution->period_id])
            ->with('success', 'Distribusi diupdate.');
    }

    public function destroy(Distribution $distribution)
    {
        $periodId = $distribution->period_id;
        $distribution->delete();

        return redirect()
            ->route('distributions.index', ['period_id' => $periodId])
            ->with('success', 'Distribusi dihapus.');
    }

    public function recordPayment(Request $request, Distribution $distribution)
    {
        $request->validate(['paid_amount' => 'required|integer|min:1']);

        $total    = $distribution->qty * $distribution->price_per_unit;
        $newPaid  = min($distribution->paid_amount + $request->paid_amount, $total);
        $status   = $newPaid >= $total ? 'paid' : 'partial';

        $distribution->update(['paid_amount' => $newPaid, 'payment_status' => $status]);

        return back()->with('success', 'Setoran dicatat.');
    }

    // ──────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    /** Throw jika periode sudah ditutup. */
    private function guardClosedPeriod(int $periodId): void
    {
        $period = Period::findOrFail($periodId);
        abort_if($period->status === 'closed', 403, 'Periode sudah ditutup.');
    }

    /** Hitung paid_amount berdasarkan status pembayaran. */
    private function resolvePaidAmount(string $status, int $qty, int $price, int $partial): int
    {
        return $status === 'paid' ? $qty * $price : $partial;
    }

    /** Total DO qty bulan ini (bukan carry-over). */
    private function getTotalDoQty(Period $period): int
    {
        return (int) DeliveryOrder::where('period_id', $period->id)
            ->where(fn($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->sum('qty');
    }

    /**
     * Bangun grid: customer_id → day → akumulasi distribusi.
     *
     * @return array<int, array<int, array{qty:int, total_value:int, paid_amount:int, payment_status:string, ids:int[]}>>
     */
    private function buildGrid(Collection $distributions): array
    {
        $grid = [];

        foreach ($distributions as $d) {
            $day = $d->dist_date->day;
            $cid = $d->customer_id;

            if (! isset($grid[$cid][$day])) {
                $grid[$cid][$day] = [
                    'qty'            => 0,
                    'total_value'    => 0,
                    'paid_amount'    => 0,
                    'payment_status' => $d->payment_status,
                    'ids'            => [],
                ];
            }

            $grid[$cid][$day]['qty']         += $d->qty;
            $grid[$cid][$day]['total_value']  += $d->qty * $d->price_per_unit;
            $grid[$cid][$day]['paid_amount']  += $d->paid_amount;
            $grid[$cid][$day]['ids'][]         = $d->id;

            // Jika ada satu baris tidak lunas, status cell jadi non-paid
            if ($d->payment_status !== 'paid') {
                $grid[$cid][$day]['payment_status'] = $d->payment_status;
            }
        }

        return $grid;
    }

    /**
     * Hitung total qty / nilai / bayar per customer.
     *
     * @return array<int, array{qty:int, total_value:int, paid:int}>
     */
    private function buildCustomerTotals(Collection $customers, Collection $distributions): array
    {
        $totals = [];

        foreach ($customers as $c) {
            $rows = $distributions->where('customer_id', $c->id);
            $totals[$c->id] = [
                'qty'         => $rows->sum('qty'),
                'total_value' => $rows->sum(fn($d) => $d->qty * $d->price_per_unit),
                'paid'        => $rows->sum('paid_amount'),
            ];
        }

        return $totals;
    }

    /**
     * Data harian untuk chart bar utama.
     * Hanya hari dengan qty > 0 yang dimasukkan.
     *
     * @return array{labels:int[], qty:int[], val:int[], paid:int[]}
     */
    private function buildDailyChartData(array $grid, int $daysInMonth): array
    {
        $labels = $qty = $val = $paid = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dq = collect($grid)->sum(fn($days) => $days[$d]['qty']          ?? 0);
            $dv = collect($grid)->sum(fn($days) => $days[$d]['total_value']  ?? 0);
            $dp = collect($grid)->sum(fn($days) => $days[$d]['paid_amount']  ?? 0);

            if ($dq > 0) {
                $labels[] = $d;
                $qty[]    = $dq;
                $val[]    = $dv;
                $paid[]   = $dp;
            }
        }

        return compact('labels', 'qty', 'val', 'paid');
    }

    /**
     * Ringkasan KPI + indikator + ranking piutang.
     *
     * @param array{qty:int[], val:int[], paid:int[], labels:int[]} $chartData
     */
    private function buildSummaryData(array $customerTotals, array $chartData, int $daysInMonth): array
    {
        $allQty    = array_sum(array_column($customerTotals, 'qty'));
        $allVal    = array_sum(array_column($customerTotals, 'total_value'));
        $allPaid   = array_sum(array_column($customerTotals, 'paid'));
        $piutang   = $allVal - $allPaid;
        $activeDays = count(array_filter($chartData['qty']));

        // Nilai/kas harian per hari aktif
        $hariLabels = $hariNilai = $hariKas = $hariPiutang = $hariQty = $hariHarga = [];
        foreach ($chartData['labels'] as $i => $d) {
            $q = $chartData['qty'][$i];
            $v = $chartData['val'][$i];
            $p = $chartData['paid'][$i];
            $hariLabels[]  = $d;
            $hariNilai[]   = $v;
            $hariKas[]     = $p;
            $hariPiutang[] = $v - $p;
            $hariQty[]     = $q;
            $hariHarga[]   = $q > 0 ? round($v / $q) : 0;
        }

        $avgTabHar   = $activeDays > 0 ? round($allQty / $activeDays, 1) : 0;
        $avgHargaC   = $allQty > 0 ? round($allVal / $allQty) : 0;
        $rasioLunas  = $allVal > 0 ? ($allPaid / $allVal * 100) : 0;
        $avgNilaiH   = $activeDays > 0 ? round($allVal / $activeDays) : 0;
        $avgKasH     = $activeDays > 0 ? round($allPaid / $activeDays) : 0;

        $hargaMin      = count($hariHarga) ? min($hariHarga) : 0;
        $hargaMax      = count($hariHarga) ? max($hariHarga) : 0;
        $variasiHarga  = $hargaMin > 0 ? round(($hargaMax - $hargaMin) / $hargaMin * 100, 1) : 0;
        $rasioAktif    = round($activeDays / 26 * 100);

        return [
            // Totals
            'allQty'       => $allQty,
            'allVal'       => $allVal,
            'allPaid'      => $allPaid,
            'piutang'      => $piutang,
            'activeDays'   => $activeDays,
            // Averages
            'avgTabHar'    => $avgTabHar,
            'avgHargaC'    => $avgHargaC,
            'avgNilaiH'    => $avgNilaiH,
            'avgKasH'      => $avgKasH,
            // Hari-series (untuk chart)
            'hariLabels'   => $hariLabels,
            'hariNilai'    => $hariNilai,
            'hariKas'      => $hariKas,
            'hariPiutang'  => $hariPiutang,
            'hariQty'      => $hariQty,
            'hariHarga'    => $hariHarga,
            // Indikator
            'rasioLunas'   => $rasioLunas,
            'rasioAktif'   => $rasioAktif,
            'variasiHarga' => $variasiHarga,
            'hargaMin'     => $hargaMin,
            'hargaMax'     => $hargaMax,
        ];
    }

    /**
     * Data proyeksi kumulatif akhir bulan.
     *
     * @param int[] $qtyPerDay   Qty per hari aktif
     * @param int[] $dayLabels   Label hari (angka tanggal)
     */
    private function buildProjectionData(array $qtyPerDay, array $dayLabels, int $daysInMonth): array
    {
        $todayDay   = now()->day;
        $totalAktual = array_sum($qtyPerDay);
        $activeDays  = count(array_filter($qtyPerDay));

        $mean = $activeDays > 0 ? $totalAktual / $activeDays : 0;
        $std  = $activeDays > 1
            ? sqrt(array_sum(array_map(fn($q) => ($q - $mean) ** 2, $qtyPerDay)) / $activeDays)
            : 0;

        $sisaKalender = $daysInMonth - $todayDay;
        $rasioAktif   = $todayDay > 0 ? $activeDays / $todayDay : 0;
        $estHariSisa  = max((int) round($rasioAktif * $sisaKalender), 0);

        $projTren = (int) round($totalAktual + $estHariSisa * $mean);
        $projMaks = (int) round($totalAktual + $estHariSisa * ($mean + $std));
        $projMin  = (int) round($totalAktual + $estHariSisa * max($mean - $std, 0));
        $projPct  = $projTren > 0 ? min((int) round($totalAktual / $projTren * 100), 100) : 0;

        // Kumulatif aktual per tanggal
        $cumByDay = [];
        $cumRun   = 0;
        foreach ($dayLabels as $i => $d) {
            $cumRun      += $qtyPerDay[$i];
            $cumByDay[$d] = $cumRun;
        }

        // Series per hari untuk chart
        $chartLabels = $chartAktual = $chartTren = $chartMin = $chartMaks = [];
        $lastCum = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $chartLabels[] = $d;
            if ($d <= $todayDay) {
                $v       = $cumByDay[$d] ?? $lastCum;
                $lastCum = $v;
                $chartAktual[] = $v;
                $chartTren[]   = $v;
                $chartMin[]    = $v;
                $chartMaks[]   = $v;
            } else {
                $ahead         = $d - $todayDay;
                $estDays       = (int) round($ahead * $rasioAktif);
                $chartAktual[] = null;
                $chartTren[]   = (int) round($totalAktual + $estDays * $mean);
                $chartMin[]    = (int) round($totalAktual + $estDays * max($mean - $std, 0));
                $chartMaks[]   = (int) round($totalAktual + $estDays * ($mean + $std));
            }
        }

        return [
            'todayDay'      => $todayDay,
            'totalAktual'   => $totalAktual,
            'activeDays'    => $activeDays,
            'mean'          => round($mean, 1),
            'std'           => round($std, 1),
            'sisaKalender'  => $sisaKalender,
            'estHariSisa'   => $estHariSisa,
            'projTren'      => $projTren,
            'projMaks'      => $projMaks,
            'projMin'       => $projMin,
            'projPct'       => $projPct,
            // Chart series
            'chartLabels'   => $chartLabels,
            'chartAktual'   => $chartAktual,
            'chartTren'     => $chartTren,
            'chartMin'      => $chartMin,
            'chartMaks'     => $chartMaks,
        ];
    }
}
