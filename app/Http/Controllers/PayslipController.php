<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\DailyExpense;
use App\Models\Distribution;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();
        $couriers = Courier::where('is_active', true)->get();

        return view('payslip.index', compact('period', 'periods', 'couriers'));
    }

    public function show(Request $request, Courier $courier)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        // ── PENDAPATAN KOTOR ─────────────────────────────────────────────

        // 1. Upah distribusi tabung (500 × qty)
        $distributions = Distribution::where('period_id', $period->id)->where('courier_id', $courier->id)->orderBy('dist_date')->get();

        $totalTabung = $distributions->sum('qty');
        $upahTabung = $totalTabung * $courier->wage_per_unit;

        // 2. Hari distribusi (hari unik yang ada distribusi)
        $hariDistribusi = $distributions->pluck('dist_date')->map(fn($d) => $d->format('Y-m-d'))->unique()->count();

        // 3. Tunjangan transportasi (10.000/hari distribusi)
        $tunjanganTransportasi = $hariDistribusi * 10000;

        // 4. Tunjangan perawatan kendaraan (flat, bisa dikonfigurasi)
        $tunjanganKendaraan = 0; // default 0, bisa diedit via request

        $totalPendapatanKotor = $upahTabung + $tunjanganTransportasi + $tunjanganKendaraan;

        // ── POTONGAN ────────────────────────────────────────────────────

        // Ambil pengeluaran bensin, rokok, makan dari cashflow harian
        $bensinTotal = DailyExpense::where('period_id', $period->id)->where('category', 'bensin')->sum('amount');
        $rokokTotal = DailyExpense::where('period_id', $period->id)->where('category', 'rokok')->sum('amount');
        $makanTotal = DailyExpense::where('period_id', $period->id)->where('category', 'makan')->sum('amount');

        // Breakdown bensin per hari untuk tooltip jatah vs terpakai
        $bensinByDay = DailyExpense::where('period_id', $period->id)->where('category', 'bensin')->selectRaw('DAY(expense_date) as day, SUM(amount) as total')->groupBy('day')->pluck('total', 'day')->toArray();

        // Jatah bensin = 10.000/hari × hari distribusi
        $jatahBensin = $hariDistribusi * 10000;
        // Terpakai = aktual dari cashflow
        $bensinTerpakai = $bensinTotal;
        // Potongan bensin = selisih jika terpakai > jatah
        $potonganBensin = max(0, $bensinTerpakai - $jatahBensin);

        // Potongan makan + rokok langsung sebagai reimbursement
        $potonganMakanRokok = $rokokTotal + $makanTotal;

        $totalPotongan = $potonganBensin + $potonganMakanRokok;

        // ── GAJI BERSIH ─────────────────────────────────────────────────
        $gajiBersih = $totalPendapatanKotor - $totalPotongan;

        // ── DATA DISTRIBUSI PER HARI (untuk detail) ──────────────────────
        $distByDay = $distributions->groupBy(fn($d) => $d->dist_date->format('Y-m-d'))->map(fn($rows) => $rows->sum('qty'));

        return view('payslip.show', compact('courier', 'period', 'periods', 'distributions', 'totalTabung', 'upahTabung', 'hariDistribusi', 'tunjanganTransportasi', 'tunjanganKendaraan', 'totalPendapatanKotor', 'bensinTotal', 'rokokTotal', 'makanTotal', 'jatahBensin', 'bensinTerpakai', 'potonganBensin', 'potonganMakanRokok', 'totalPotongan', 'gajiBersih', 'distByDay'));
    }

    public function print(Request $request, Courier $courier)
    {
        // Sama dengan show tapi view print-friendly (tanpa navbar)
        $request->merge(['print' => true]);
        return $this->show($request, $courier);
    }
}