<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContractDistribution;
use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Period;
use Illuminate\Http\Request;

class ContractDistributionController extends Controller
{
    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        if (!$periodId) {
            return redirect()->route('periods.index')
                ->with('error', 'Buat periode terlebih dahulu.');
        }

        $period  = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        $contractCustomers = Customer::where('type', 'contract')
            ->where('is_active', true)
            ->with(['outlet'])
            ->get();

        $contracts = [];
        foreach ($contractCustomers as $customer) {
            $cd = ContractDistribution::syncFromDistributions($period, $customer);

            // Attach distribusi harian untuk timeline
            $cd->dailyDistributions = Distribution::where('period_id', $period->id)
                ->where('customer_id', $customer->id)
                ->orderBy('dist_date')
                ->get();

            $contracts[$customer->id] = $cd;
        }

        return view('contract-distributions.index', compact(
            'period', 'periods', 'contractCustomers', 'contracts'
        ));
    }

    // ── UPDATE (edit manual: qty_ditinggalkan, bayar_tunai, catatan, cutoff) ──
    public function update(Request $request, ContractDistribution $contractDistribution)
    {
        $request->validate([
            'qty_ditinggalkan'    => 'nullable|integer|min:0',
            'bayar_kontrak_tunai' => 'nullable|integer|min:0',
            'catatan_distribusi'  => 'nullable|string|max:1000',
            'catatan_kontrak'     => 'nullable|string|max:1000',
            'catatan_khusus'      => 'nullable|string|max:1000',
            'cutoff_date'         => 'nullable|date',
        ]);

        $cd   = $contractDistribution;
        $data = [];

        // Angga: tabung yang sengaja tidak ditagih → offset ke kontrak flat
        if ($request->has('qty_ditinggalkan')) {
            $qty = max(0, (int) $request->qty_ditinggalkan);
            $data['qty_ditinggalkan']   = $qty;
            $data['nilai_ditinggalkan'] = $qty * $cd->price_per_unit;
        }

        // Angga: bayar sisa kontrak tunai (jika offset belum cukup)
        if ($request->has('bayar_kontrak_tunai')) {
            $data['bayar_kontrak_tunai'] = max(0, (int) $request->bayar_kontrak_tunai);
        }

        // Catatan bebas
        foreach (['catatan_distribusi', 'catatan_kontrak', 'catatan_khusus'] as $f) {
            if ($request->has($f)) {
                $data[$f] = $request->$f;
            }
        }

        // Cutoff
        if ($request->filled('cutoff_date')) {
            $data['cutoff_date'] = $request->cutoff_date;
            $data['is_cutoff']   = true;
        }

        if (!empty($data)) {
            $cd->update($data);
        }

        // Hitung ulang saldo setelah perubahan
        $cd->recalcSaldo();

        return back()->with('success', 'Data kontrak berhasil diperbarui.');
    }

    // ── SELESAIKAN (catat pembayaran ke / dari customer) ─────────────────────
    public function selesaikan(Request $request, ContractDistribution $contractDistribution)
    {
        $request->validate([
            'nominal' => 'required|integer|min:1',
            'catatan' => 'nullable|string|max:500',
        ]);

        $cd           = $contractDistribution;
        $nominal      = (int) $request->nominal;
        $totalSudah   = $cd->nominal_diselesaikan + $nominal;
        $targetSaldo  = abs($cd->saldo_bersih);
        $selesai      = $totalSudah >= $targetSaldo;

        // Jangan melebihi saldo
        if ($totalSudah > $targetSaldo) {
            $totalSudah = $targetSaldo;
        }

        $catatanBaru = $request->catatan;
        $catatanLama = $cd->catatan_kontrak;
        $catatanFinal = $catatanBaru
            ? ($catatanLama ? $catatanLama . "\n" . $catatanBaru : $catatanBaru)
            : $catatanLama;

        $cd->update([
            'nominal_diselesaikan' => $totalSudah,
            'sudah_diselesaikan'   => $selesai,
            'catatan_kontrak'      => $catatanFinal,
        ]);

        $msg = $selesai
            ? 'Saldo bersih sudah diselesaikan sepenuhnya. ✓'
            : 'Dicatat Rp ' . number_format($nominal) . '. Sisa: Rp ' . number_format($cd->fresh()->sisaBelumDiselesaikan());

        return back()->with('success', $msg);
    }

    // ── RESET PENYELESAIAN ────────────────────────────────────────────────────
    public function resetSelesaikan(ContractDistribution $contractDistribution)
    {
        $contractDistribution->update([
            'nominal_diselesaikan' => 0,
            'sudah_diselesaikan'   => false,
        ]);
        return back()->with('success', 'Status penyelesaian direset.');
    }

    // ── CUTOFF ────────────────────────────────────────────────────────────────
    public function cutoff(Request $request, ContractDistribution $contractDistribution)
    {
        $request->validate(['cutoff_date' => 'required|date']);
        $contractDistribution->update([
            'is_cutoff'   => true,
            'cutoff_date' => $request->cutoff_date,
        ]);
        return back()->with('success', 'Cutoff berhasil dicatat.');
    }

    // ── SYNC PAKSA ────────────────────────────────────────────────────────────
    public function sync(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period   = Period::findOrFail($periodId);

        $count = 0;
        Customer::where('type', 'contract')->where('is_active', true)->get()
            ->each(function ($c) use ($period, &$count) {
                ContractDistribution::syncFromDistributions($period, $c);
                $count++;
            });

        return back()->with('success', "Sync selesai — {$count} customer kontrak diperbarui.");
    }
}
