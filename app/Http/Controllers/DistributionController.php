<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\Period;
use App\Models\Saving;
use App\Services\CashflowSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DistributionController extends Controller
{
    public function __construct(private CashflowSummaryService $cashflowSummary) {}
    const BASE_PRICE = 16000;
    const HPP_PER_TABUNG = 16_000;
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

        // ── Data per-hari untuk seluruh rentang bulan (dipakai di Blade: avgPriceByDay,
        //    selisih piutang per hari) — dihitung SEKALI di sini dalam satu pass, supaya
        //    Blade tinggal lookup array, bukan meng-iterasi ulang $grid tiap baris tabel.
        $dailyFull = $this->buildFullDailyBreakdown($grid, $daysInMonth);

        return view('distributions.index', compact(
            'period', 'periods', 'distributions',
            'customers', 'couriers', 'daysInMonth',
            'grid', 'customerTotals', 'totalDoQty',
            'chartData', 'summaryData', 'projectionData',
            'dailyFull',
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

    public function compare()
    {
        $periods = Period::orderBy('year')->orderBy('month')->get();
        $customers = Customer::orderBy('name')->get(); // semua customer (aktif/nonaktif) agar histori periode lama tetap terhitung

        $rows = $periods->map(function ($period) use ($customers) {
            $distributions = Distribution::where('period_id', $period->id)
                ->orderBy('dist_date')
                ->get();

            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);
            $totalDoQty  = $this->getTotalDoQty($period);

            $grid           = $this->buildGrid($distributions);
            $customerTotals = $this->buildCustomerTotals($customers, $distributions);
            $chartData      = $this->buildDailyChartData($grid, $daysInMonth);
            $s              = $this->buildSummaryData($customerTotals, $chartData, $daysInMonth);
            $cf = $this->cashflowSummary->forPeriod($period);

            // ── Saving / Surplus ──
            $savings   = Saving::where('period_id', $period->id)->get();
            $savingIn  = (int) $savings->where('type', 'in')->sum('amount');
            $savingOut = (int) $savings->where('type', 'out')->sum('amount');
            $savingOpening = (int) $period->opening_surplus;
            $savingBalance = $savingOpening + $savingIn - $savingOut;

             // ── Delivery Order (baru) ──
            $doReceivedQty = (int) DeliveryOrder::where('period_id', $period->id)
                ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
                ->sum('qty');

            $doCarryOverQty = (int) DeliveryOrder::where('period_id', $period->id)
                ->where('notes', 'like', '%Carry-over%')
                ->sum('qty');

            $doTotalQty     = $doReceivedQty + $doCarryOverQty;
            $doDistributed  = $s['allQty']; // total tabung terdistribusi (sudah ada)
            $doSisaStok     = $period->opening_stock + $doTotalQty - $doDistributed;
            $doUtilisasi    = $doTotalQty > 0 ? round($doDistributed / $doTotalQty * 100, 1) : 0.0;

            // ── Rekonsiliasi & Analisis (baru) ──
            $profitBersih       = $s['allMargin'] - $cf['totalExpense'] - $cf['totalAdminFees'];
            $totalUangDipegang  = $cf['netKas'] + $cf['finalBankBal'] + $savingBalance;
            $nilaiStokAtCost    = $doSisaStok * self::HPP_PER_TABUNG; // ganti konstanta sesuai punya kamu
            $totalKekayaan      = $totalUangDipegang + $s['piutang'] + $nilaiStokAtCost;

            // Cross-check: harus 0 kalau data konsisten (sumber sama, jalur beda)
            $selisihIncome = $s['allPaid']   - $cf['totalIncome'];
            $selisihMargin = $s['allMargin'] - $cf['totalMargin'];
            $selisihDoQty  = $totalDoQty     - $doReceivedQty;

            return [
                'period_id'    => $period->id,
                'label'        => $period->label,
                'status'       => $period->status,
                'allQty'       => $s['allQty'],
                'allVal'       => $s['allVal'],
                'allPaid'      => $s['allPaid'],
                'piutang'      => $s['piutang'],
                'allMargin'    => $s['allMargin'],
                'avgTabHar'    => $s['avgTabHar'],
                'activeDays'   => $s['activeDays'],
                'daysInMonth'  => $daysInMonth,
                'rasioLunas'   => $s['rasioLunas'],
                'avgHargaC'    => $s['avgHargaC'],
                'totalDoQty'   => $totalDoQty,
                'openingStock' => $period->opening_stock,
                'stokTersedia' => $period->opening_stock + $totalDoQty - $s['allQty'],

                // ── cashflow ──
                'cfOpeningCash'  => $cf['openingCash'],   // ← INI YANG KETINGGALAN
                'cfIncome'       => $cf['totalIncome'],
                'cfExpense'      => $cf['totalExpense'],
                'cfMargin'       => $cf['totalMargin'],
                'cfDeposits'     => $cf['totalDeposits'],
                'cfAdminFees'    => $cf['totalAdminFees'],
                'cfTransferred'  => $cf['totalTransferred'],
                'cfSurplus'      => $cf['totalSurplus'],
                'cfNetKas'       => $cf['netKas'],
                'cfBankBal'      => $cf['finalBankBal'],
                'cfNetTotal'     => $cf['netTotal'],
                'cfRasioOps'     => $cf['rasioOperasional'],
                'cfRasioGross'   => $cf['rasioGross'],

                // ── saving / surplus (baru) ──
                'svOpening' => $savingOpening,
                'svIn'      => $savingIn,
                'svOut'     => $savingOut,
                'svBalance' => $savingBalance,

                // ── delivery order (baru) ──
                'doReceivedQty'  => $doReceivedQty,
                'doCarryOverQty' => $doCarryOverQty,
                'doTotalQty'     => $doTotalQty,
                'doDistributed'  => $doDistributed,
                'doSisaStok'     => $doSisaStok,
                'doUtilisasi'    => $doUtilisasi,

                // ── rekonsiliasi (baru) ──
                'profitBersih'      => $profitBersih,
                'totalUangDipegang' => $totalUangDipegang,
                'nilaiStokAtCost'   => $nilaiStokAtCost,
                'totalKekayaan'      => $totalKekayaan,
                'selisihIncome'      => $selisihIncome,
                'selisihMargin'      => $selisihMargin,
                'selisihDoQty'       => $selisihDoQty,
                'isKonsisten'        => $selisihIncome === 0 && $selisihMargin === 0 && $selisihDoQty === 0,
            ];
        })->values();

        // Growth % dibanding periode sebelumnya
        $rows = $rows->map(function ($row, $i) use ($rows) {
            $prev = $i > 0 ? $rows[$i - 1] : null;

            $row['qtyGrowth'] = ($prev && $prev['allQty'] > 0)
                ? round((($row['allQty'] - $prev['allQty']) / $prev['allQty']) * 100, 1)
                : null;

            $row['valGrowth'] = ($prev && $prev['allVal'] > 0)
                ? round((($row['allVal'] - $prev['allVal']) / $prev['allVal']) * 100, 1)
                : null;

            $row['piutangGrowth'] = ($prev && $prev['piutang'] > 0)
                ? round((($row['piutang'] - $prev['piutang']) / $prev['piutang']) * 100, 1)
                : null;

            $row['cfNetTotalGrowth'] = ($prev && $prev['cfNetTotal'] != 0)
                ? round((($row['cfNetTotal'] - $prev['cfNetTotal']) / abs($prev['cfNetTotal'])) * 100, 1)
                : null;

            $row['svBalanceGrowth'] = ($prev && $prev['svBalance'] != 0)
                ? round((($row['svBalance'] - $prev['svBalance']) / abs($prev['svBalance'])) * 100, 1)
                : null;

            $row['doReceivedGrowth'] = ($prev && $prev['doReceivedQty'] > 0)
                ? round((($row['doReceivedQty'] - $prev['doReceivedQty']) / $prev['doReceivedQty']) * 100, 1)
                : null;

            return $row;
        });

        // Ringkasan keseluruhan
        $grand = [
            'allQty'           => $rows->sum('allQty'),
            'allVal'           => $rows->sum('allVal'),
            'allPaid'          => $rows->sum('allPaid'),
            'piutang'          => $rows->sum('piutang'),
            'allMargin'        => $rows->sum('allMargin'),
            'avgQtyPerPeriod'  => $rows->count() > 0 ? round($rows->avg('allQty')) : 0,
            'bestQtyPeriod'    => $rows->sortByDesc('allQty')->first(),
            'worstLunasPeriod' => $rows->sortBy('rasioLunas')->first(),

            // cashflow
            'cfOpeningCash'      => $rows->sum('cfOpeningCash'),
            'cfIncome'           => $rows->sum('cfIncome'),
            'cfExpense'          => $rows->sum('cfExpense'),
            'cfMargin'           => $rows->sum('cfMargin'),
            'cfDeposits'         => $rows->sum('cfDeposits'),
            'cfAdminFees'        => $rows->sum('cfAdminFees'),
            'cfTransferred'      => $rows->sum('cfTransferred'),
            'cfSurplus'          => $rows->sum('cfSurplus'),
            'cfNetKas'           => $rows->sum('cfNetKas'),
            'cfBankBal'          => $rows->sum('cfBankBal'),
            'cfNetTotal'         => $rows->sum('cfNetTotal'),
            'bestCashflowPeriod' => $rows->sortByDesc('cfNetTotal')->first(),

             // saving / surplus (baru)
            'svIn'       => $rows->sum('svIn'),
            'svOut'      => $rows->sum('svOut'),
            'svBalance'  => $rows->last()['svBalance'] ?? 0,

            'doReceivedQty'  => $rows->sum('doReceivedQty'),
            'doCarryOverQty' => $rows->sum('doCarryOverQty'),
            'doTotalQty'     => $rows->sum('doTotalQty'),
            'doDistributed'  => $rows->sum('doDistributed'),
            'avgUtilisasi'   => $rows->count() > 0 ? round($rows->avg('doUtilisasi'), 1) : 0,

            'profitBersih'         => $rows->sum('profitBersih'),               // additive antar periode, aman di-sum
            'totalUangDipegangKini' => $rows->last()['totalUangDipegang'] ?? 0,  // snapshot AKHIR, bukan sum
            'totalKekayaanKini'     => $rows->last()['totalKekayaan'] ?? 0,      // snapshot AKHIR, bukan sum
            'adaAnomali'            => $rows->contains(fn($r) => !$r['isKonsisten']),
        ];

        $chart = [
            'labels'     => $rows->pluck('label'),
            'qty'        => $rows->pluck('allQty'),
            'val'        => $rows->pluck('allVal'),
            'paid'       => $rows->pluck('allPaid'),
            'piutang'    => $rows->pluck('piutang'),
            'margin'     => $rows->pluck('allMargin'),
            'rasioLunas' => $rows->pluck('rasioLunas'),

            // cashflow (array per-periode, untuk dipetakan ke js chart)
            'cfIncome'   => $rows->pluck('cfIncome'),
            'cfExpense'  => $rows->pluck('cfExpense'),
            'cfNetTotal' => $rows->pluck('cfNetTotal'),
        ];

        return view('distributions.compare', compact('rows', 'chart', 'grand'));
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
     * OPTIMASI: dulu di dalam foreach($customers) melakukan
     * $distributions->where('customer_id', $c->id) yang men-scan ULANG
     * seluruh koleksi distribusi untuk tiap customer — O(customers × distribusi).
     * Sekarang di-groupBy SEKALI di awal, jadi tiap customer tinggal ambil
     * grup miliknya — O(distribusi) total, jauh lebih ringan saat data besar.
     *
     * @return array<int, array{qty:int, total_value:int, paid:int}>
     */
    private function buildCustomerTotals(Collection $customers, Collection $distributions): array
    {
        $grouped = $distributions->groupBy('customer_id');
        $totals  = [];

        foreach ($customers as $c) {
            $rows        = $grouped->get($c->id, collect());
            $qty         = $rows->sum('qty');
            $total_value = $rows->sum(fn($d) => $d->qty * $d->price_per_unit);
            $base_cost   = $qty * self::BASE_PRICE;

            $totals[$c->id] = [
                'qty'         => $qty,
                'total_value' => $total_value,
                'paid'        => $rows->sum('paid_amount'),
                'base_cost'   => $base_cost,
                'margin'       => $total_value - $base_cost,   // margin kotor
            ];
        }

        return $totals;
    }

    /**
     * Data harian untuk chart bar utama.
     * Hanya hari dengan qty > 0 yang dimasukkan.
     *
     * OPTIMASI: dulu untuk tiap hari (1..daysInMonth) melakukan
     * collect($grid)->sum(...) yang meng-iterasi ULANG seluruh customer
     * di $grid — O(days × customers). Sekarang cukup satu kali loop
     * menyusuri $grid dan mengakumulasi ke array per-hari — O(total sel grid),
     * jauh lebih murah kalau jumlah customer banyak.
     *
     * @return array{labels:int[], qty:int[], val:int[], paid:int[]}
     */
    private function buildDailyChartData(array $grid, int $daysInMonth): array
    {
        $dailyQty  = array_fill(1, $daysInMonth, 0);
        $dailyVal  = array_fill(1, $daysInMonth, 0);
        $dailyPaid = array_fill(1, $daysInMonth, 0);

        foreach ($grid as $days) {
            foreach ($days as $day => $cell) {
                $dailyQty[$day]  += $cell['qty'];
                $dailyVal[$day]  += $cell['total_value'];
                $dailyPaid[$day] += $cell['paid_amount'];
            }
        }

        $labels = $qty = $val = $paid = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            if ($dailyQty[$d] > 0) {
                $labels[] = $d;
                $qty[]    = $dailyQty[$d];
                $val[]    = $dailyVal[$d];
                $paid[]   = $dailyPaid[$d];
            }
        }

        return compact('labels', 'qty', 'val', 'paid');
    }

    /**
     * Rincian per-hari untuk SELURUH rentang bulan (termasuk hari kosong),
     * dipakai oleh Blade untuk baris "Avg Harga/Tab per hari" dan
     * "Selisih (Piutang) per hari" di tabel Rekap per Customer.
     *
     * Ini menggantikan 2 loop terpisah yang sebelumnya ada langsung di
     * Blade (masing-masing meng-iterasi ulang $grid per hari) — sekarang
     * dihitung SEKALI di sini bersamaan dengan buildDailyChartData, dalam
     * satu pass yang sama, lalu Blade tinggal array-lookup per hari.
     *
     * @return array<int, array{qty:int, val:int, paid:int, avgHarga:int, selisih:int}>
     */
    private function buildFullDailyBreakdown(array $grid, int $daysInMonth): array
    {
        $dailyQty  = array_fill(1, $daysInMonth, 0);
        $dailyVal  = array_fill(1, $daysInMonth, 0);
        $dailyPaid = array_fill(1, $daysInMonth, 0);

        foreach ($grid as $days) {
            foreach ($days as $day => $cell) {
                $dailyQty[$day]  += $cell['qty'];
                $dailyVal[$day]  += $cell['total_value'];
                $dailyPaid[$day] += $cell['paid_amount'];
            }
        }

        $result = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $qty = $dailyQty[$d];
            $val = $dailyVal[$d];
            $paid = $dailyPaid[$d];

            $result[$d] = [
                'qty'      => $qty,
                'val'      => $val,
                'paid'     => $paid,
                'avgHarga' => $qty > 0 ? round($val / $qty) : 0,
                'selisih'  => $val - $paid,
            ];
        }

        return $result;
    }

    /**
     * Ringkasan KPI + indikator + ranking piutang.
     *
     * @param array{qty:int[], val:int[], paid:int[], labels:int[]} $chartData
     */
    private function buildSummaryData(array $customerTotals, array $chartData, int $daysInMonth): array
    {
        $allQty      = array_sum(array_column($customerTotals, 'qty'));
        $allVal      = array_sum(array_column($customerTotals, 'total_value'));
        $allPaid     = array_sum(array_column($customerTotals, 'paid'));
        $allBaseCost = array_sum(array_column($customerTotals, 'base_cost'));
        $allMargin    = array_sum(array_column($customerTotals, 'margin'));
        $piutang     = $allVal - $allPaid;
        $activeDays  = count(array_filter($chartData['qty']));

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
            'allBaseCost'  => $allBaseCost,   // ← baru
            'allMargin'     => $allMargin,       // ← baru
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
