<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\Outlet;
use App\Models\OutletContractPayment;
use App\Models\DeliveryOrder;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PeriodController extends Controller
{
    public function index()
    {
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();
        return view('periods.index', compact('periods'));
    }

    public function create()
    {
        $latest = Period::orderByDesc('year')->orderByDesc('month')->first();
        $suggested = $latest
            ? Carbon::create($latest->year, $latest->month)->addMonth()
            : Carbon::now()->startOfMonth();

        $outlets = Outlet::where('is_active', true)->get();

        // Hitung sisa stok dari periode sebelumnya
        $prevStockSisa = 0;
        if ($latest) {
            $prevStockSisa = $latest->opening_stock
                + $latest->totalDoQty()
                - $latest->totalDistQty();
        }

        // Ambil DO dari periode sebelumnya yang belum lunas (sudah tercatat di sistem)
        $prevUnpaidDOs = collect();
        if ($latest) {
            $prevUnpaidDOs = DeliveryOrder::with('outlet')
                ->where('period_id', $latest->id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->orderBy('do_date')
                ->get();
        }

        $prevUnpaidQty = $prevUnpaidDOs->sum('qty');

        // Default carryover rows untuk Alpine (kosong jika sudah ada di sistem)
        $carryoverRows = [['outlet_id' => '', 'qty' => 0, 'price_per_unit' => 16000]];

        // Saat create, belum ada existing carry-over (periode belum ada)
        $existingCarryoverDOs = collect();

        return view('periods.create', compact(
            'suggested', 'latest', 'outlets',
            'prevStockSisa', 'prevUnpaidDOs', 'prevUnpaidQty',
            'carryoverRows', 'existingCarryoverDOs'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'year'                  => 'required|integer|min:2020|max:2100',
            'month'                 => 'required|integer|min:1|max:12',
            'opening_stock'         => 'required|integer|min:0',
            'opening_cash'          => 'required|integer|min:0',
            'opening_penampung'     => 'required|integer|min:0',
            'opening_external_debt' => 'required|integer|min:0',
            'opening_surplus'       => 'nullable|integer|min:0',
            'carryover'             => 'nullable|array',
            'carryover.*.outlet_id'     => 'required_with:carryover|exists:outlets,id',
            'carryover.*.qty'           => 'required_with:carryover|integer|min:1',
            'carryover.*.price_per_unit'=> 'required_with:carryover|integer|min:1000',
        ]);

        $monthNames = [
            1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
            7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
        ];

        // Hitung total carry-over qty untuk opening_do_unpaid_qty
        $carryovers = collect($request->carryover ?? [])->filter(fn($r) => !empty($r['outlet_id']) && $r['qty'] > 0);
        $carryoverQty = $carryovers->sum('qty');

        $period = Period::create([
            'year'                  => $request->year,
            'month'                 => $request->month,
            'label'                 => $monthNames[$request->month] . ' ' . $request->year,
            'status'                => 'open',
            'opening_stock'         => $request->opening_stock,
            'opening_cash'          => $request->opening_cash,
            'opening_penampung'     => $request->opening_penampung,
            'opening_external_debt' => $request->opening_external_debt,
            'opening_surplus'       => $request->opening_surplus ?? 0,
            'opening_do_unpaid_qty' => $carryoverQty,
        ]);

        // Buat DO carry-over sebagai record nyata agar bisa dialokasikan di Transfer
        // Tanggal DO = hari terakhir bulan sebelumnya
        $lastDayPrevMonth = Carbon::create($request->year, $request->month, 1)->subDay()->format('Y-m-d');

        foreach ($carryovers as $row) {
            DeliveryOrder::create([
                'period_id'      => $period->id,
                'outlet_id'      => $row['outlet_id'],
                'do_date'        => $lastDayPrevMonth,
                'qty'            => $row['qty'],
                'price_per_unit' => $row['price_per_unit'],
                'payment_status' => 'unpaid',
                'paid_amount'    => 0,
                'notes'          => 'Carry-over piutang DO dari bulan sebelumnya',
            ]);
        }

        // Auto-generate contract payment records
        foreach (Outlet::where('contract_type', '!=', 'none')->where('is_active', true)->get() as $outlet) {
            OutletContractPayment::firstOrCreate(
                ['period_id' => $period->id, 'outlet_id' => $outlet->id],
                ['calculated_amount' => 0, 'paid_amount' => 0, 'status' => 'unpaid']
            );
        }

        return redirect()->route('periods.show', $period)
            ->with('success', "Periode {$period->label} berhasil dibuat."
                . ($carryoverQty > 0 ? " {$carryoverQty} tabung DO carry-over dicatat." : ""));
    }

    public function edit(Period $period)
    {
        if ($period->status === 'closed') {
            return redirect()->route('periods.show', $period)
                ->with('error', 'Periode sudah ditutup, tidak bisa diedit.');
        }

        $outlets = Outlet::where('is_active', true)->get();

        // DO carry-over yang sudah tercatat di periode ini
        $existingCarryoverDOs = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->where('notes', 'like', '%Carry-over%')
            ->orderBy('do_date')
            ->get();

        // DO dari periode sebelumnya yang belum lunas (info saja)
        $prevPeriod = Period::where('id', '<', $period->id)
            ->orderByDesc('id')->first();

        $prevUnpaidDOs = collect();
        if ($prevPeriod) {
            $prevUnpaidDOs = DeliveryOrder::with('outlet')
                ->where('period_id', $prevPeriod->id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->where('notes', 'not like', '%Carry-over%')
                ->get();
        }

        $prevUnpaidQty  = $prevUnpaidDOs->sum('qty');
        $prevStockSisa  = 0; // tidak relevan saat edit
        $latest         = $prevPeriod;
        $suggested      = null; // tidak dipakai di edit
        $carryoverRows  = [['outlet_id' => '', 'qty' => 0, 'price_per_unit' => 16000]];

        return view('periods.edit', compact(
            'period', 'outlets',
            'existingCarryoverDOs', 'prevUnpaidDOs', 'prevUnpaidQty',
            'prevStockSisa', 'latest', 'suggested', 'carryoverRows'
        ));
    }

    public function update(Request $request, Period $period)
    {
        if ($period->status === 'closed') {
            return redirect()->route('periods.show', $period)
                ->with('error', 'Periode sudah ditutup, tidak bisa diedit.');
        }

        $request->validate([
            'opening_stock'         => 'required|integer|min:0',
            'opening_cash'          => 'required|integer|min:0',
            'opening_penampung'     => 'required|integer|min:0',
            'opening_external_debt' => 'required|integer|min:0',
            'opening_surplus'       => 'nullable|integer|min:0',
            'carryover'             => 'nullable|array',
            'carryover.*.outlet_id'      => 'required_with:carryover|exists:outlets,id',
            'carryover.*.qty'            => 'required_with:carryover|integer|min:1',
            'carryover.*.price_per_unit' => 'required_with:carryover|integer|min:1000',
        ]);

        // Update saldo awal
        $period->update($request->only(
            'opening_stock', 'opening_cash',
            'opening_penampung', 'opening_external_debt'
        ) + ['opening_surplus' => $request->opening_surplus ?? 0]);

        // Tambahkan carry-over DO baru (jika ada)
        $carryovers = collect($request->carryover ?? [])
            ->filter(fn($r) => !empty($r['outlet_id']) && ($r['qty'] ?? 0) > 0);

        if ($carryovers->isNotEmpty()) {
            $lastDayPrevMonth = \Carbon\Carbon::create($period->year, $period->month, 1)
                ->subDay()->format('Y-m-d');

            foreach ($carryovers as $row) {
                DeliveryOrder::create([
                    'period_id'      => $period->id,
                    'outlet_id'      => $row['outlet_id'],
                    'do_date'        => $lastDayPrevMonth,
                    'qty'            => $row['qty'],
                    'price_per_unit' => $row['price_per_unit'],
                    'payment_status' => 'unpaid',
                    'paid_amount'    => 0,
                    'notes'          => 'Carry-over piutang DO dari bulan sebelumnya',
                ]);
            }

            // Update opening_do_unpaid_qty
            $totalCarryoverQty = DeliveryOrder::where('period_id', $period->id)
                ->where('notes', 'like', '%Carry-over%')
                ->sum('qty');
            $period->update(['opening_do_unpaid_qty' => $totalCarryoverQty]);
        }

        return redirect()->route('periods.show', $period)
            ->with('success', 'Periode berhasil diperbarui.'
                . ($carryovers->isNotEmpty() ? ' '.($carryovers->sum('qty')).' tabung carry-over ditambahkan.' : ''));
    }

    public function show(Period $period)
    {
        $period->load([
            'deliveryOrders.outlet',
            'distributions.customer',
            'distributions.courier',
            'dailyExpenses',
            'courierDeposits.courier',
            'accountTransfers.deliveryOrders',
            'outletContractPayments.outlet',
        ]);

        $stockIn    = $period->totalDoQty();
        $stockOut   = $period->totalDistQty();
        $stockSisa  = $period->opening_stock + $stockIn - $stockOut;

        $totalSales  = $period->totalSales();
        $totalModal  = $stockOut * 16000;
        $grossMargin = $totalSales - $totalModal;

        $totalIncome    = $totalSales;
        $totalExpense   = $period->dailyExpenses()->sum('amount');
        $netCashflow    = $totalIncome - $totalExpense;

        $totalDeposited   = $period->totalCourierDeposits();
        $totalTransferred = $period->totalTransfers();
        $penampungBalance = $period->opening_penampung + $totalDeposited - $totalTransferred;

        // Semua DO belum lunas (termasuk carry-over)
        $doUnpaid  = $period->deliveryOrders()->where('payment_status', 'unpaid')->sum('qty');
        $doPartial = $period->deliveryOrders()->where('payment_status', 'partial')->count();
        $doPaid    = $period->deliveryOrders()->where('payment_status', 'paid')->count();

        // DO carry-over (bertanda notes carry-over)
        $carryoverDOs = $period->deliveryOrders()
            ->where('notes', 'like', '%Carry-over%')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get();

        return view('periods.show', compact(
            'period',
            'stockIn', 'stockOut', 'stockSisa',
            'totalSales', 'totalModal', 'grossMargin',
            'totalIncome', 'totalExpense', 'netCashflow',
            'totalDeposited', 'totalTransferred', 'penampungBalance',
            'doUnpaid', 'doPartial', 'doPaid', 'carryoverDOs'
        ));
    }

    public function close(Period $period)
    {
        if ($period->status === 'closed') {
            return back()->with('error', 'Periode sudah ditutup.');
        }

        $period->update(['status' => 'closed']);

        return redirect()->route('periods.index')
            ->with('success', "Periode {$period->label} berhasil ditutup.");
    }

    public function destroy(Period $period)
    {
        $latest = Period::orderByDesc('year')->orderByDesc('month')->first();
        if ($latest && $latest->id !== $period->id) {
            return back()->with('error', 'Hanya periode paling akhir yang boleh dihapus.');
        }

        $label = $period->label;

        \App\Models\AccountTransfer::where('period_id', $period->id)->each(function ($tf) {
            $tf->deliveryOrders()->detach();
            $tf->delete();
        });

        $period->deliveryOrders()->delete();
        $period->distributions()->delete();
        $period->dailyExpenses()->delete();
        $period->courierDeposits()->delete();
        $period->externalDebts()->delete();
        $period->outletContractPayments()->delete();
        $period->delete();

        return redirect()->route('periods.index')
            ->with('success', "Periode {$label} berhasil dihapus.");
    }
}
