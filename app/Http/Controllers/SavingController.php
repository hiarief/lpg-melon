<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Saving;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SavingController extends Controller
{
    public function exportYear(Request $request)
    {
        $year = $request->input('year', date('Y'));
        
        return Excel::download(
            new \App\Exports\SavingsYearExport((int) $year),
            "tabungan-{$year}.xlsx"
        );
    }

    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        // Sort mode: 'transfer' (default), 'do', atau 'per_do'
        $sortMode = in_array($request->sort, ['do', 'per_do']) ? $request->sort : 'transfer';

        // Eager load accountTransfer beserta deliveryOrders-nya
        $savings = Saving::with([
            'accountTransfer.deliveryOrders',
        ])
            ->where('period_id', $period->id)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // Sort berdasarkan mode
        $savings = $savings->sortBy(function ($s) use ($sortMode) {
            if ($sortMode === 'do' || $sortMode === 'per_do') {
                // Urutkan by tanggal DO terlama
                if ($s->accountTransfer && $s->accountTransfer->deliveryOrders->isNotEmpty()) {
                    return $s->accountTransfer->deliveryOrders->min('do_date');
                }
            }
            // Default: by tanggal transfer (atau entry_date kalau manual)
            if ($s->accountTransfer) {
                return $s->accountTransfer->transfer_date;
            }
            return $s->entry_date;
        })->values();

        $totalIn  = $savings->where('type', 'in')->sum('amount');
        $totalOut = $savings->where('type', 'out')->sum('amount');
        $balance  = $period->opening_surplus + $totalIn - $totalOut;

        // Running balance per row
        $running = $period->opening_surplus;
        $rows = [];
        foreach ($savings as $s) {
            $running += $s->type === 'in' ? $s->amount : -$s->amount;

            // Ambil info DO & transfer jika ada
            $doList        = $s->accountTransfer?->deliveryOrders ?? collect();
            $transferDate  = $s->accountTransfer?->transfer_date ?? null;
            $earliestDo    = $doList->isNotEmpty() ? $doList->sortBy('do_date')->first() : null;

            // Selisih hari antara tanggal DO dan tanggal transfer
            $selisihHari = null;
            if ($earliestDo && $transferDate) {
                $selisihHari = $earliestDo->do_date->diffInDays($transferDate);
            }

            $rows[] = [
                'saving'       => $s,
                'balance'      => $running,
                'transfer_date'=> $transferDate,
                'do_list'      => $doList,
                'earliest_do'  => $earliestDo,
                'selisih_hari' => $selisihHari,
            ];
        }

        // ── Data per-DO untuk tab "Mutasi per DO" ────────────────────────────
        // Ambil semua DO yang punya saving, kelompokkan per tanggal DO
        // TIDAK dibatasi period_id — DO September yang dilunasi transfer Oktober
        // harus muncul di bulan September, bukan Oktober
        $perDoData = [];
        if ($sortMode === 'per_do') {
            // Query semua saving yang terhubung ke transfer (surplus otomatis)
            // Tidak filter by period_id — ambil semua
            $allSavingsWithTransfer = Saving::with([
                'accountTransfer.deliveryOrders',
            ])
                ->whereNotNull('account_transfer_id')
                ->get();

            // Kelompokkan per tanggal DO
            $doGroups = [];
            foreach ($allSavingsWithTransfer as $s) {
                foreach ($s->accountTransfer->deliveryOrders as $do) {
                    $doDateKey = $do->do_date->format('Y-m-d');
                    if (!isset($doGroups[$doDateKey])) {
                        $doGroups[$doDateKey] = [
                            'do_date'     => $do->do_date,
                            'outlet_name' => $do->outlet->name ?? '-',
                            'do_qty'      => 0,
                            'do_value'    => 0,
                            'surplus'     => 0,
                            'transfer_ids'=> [],
                            'saving_ids'  => [],
                        ];
                    }
                    $doGroups[$doDateKey]['do_qty']   += $do->qty;
                    $doGroups[$doDateKey]['do_value'] += $do->qty * $do->price_per_unit;
                    $doGroups[$doDateKey]['surplus']  += $s->amount;
                    $doGroups[$doDateKey]['transfer_ids'][] = $s->accountTransfer->id;
                    $doGroups[$doDateKey]['saving_ids'][]  = $s->id;
                }
            }

            // Filter: hanya tampilkan DO yang tanggalnya termasuk dalam periode ini
            $periodStart = \Carbon\Carbon::create($period->year, $period->month, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            // Sort by tanggal DO
            ksort($doGroups);

            // Format untuk view — hanya DO yang termasuk dalam periode ini
            foreach ($doGroups as $dateKey => $g) {
                $doDate = \Carbon\Carbon::parse($g['do_date']);
                if ($doDate->between($periodStart, $periodEnd)) {
                    $perDoData[] = [
                        'do_date'      => $g['do_date'],
                        'outlet_name'  => $g['outlet_name'],
                        'do_qty'       => $g['do_qty'],
                        'do_value'     => $g['do_value'],
                        'surplus'      => $g['surplus'],
                        'transfer_ids' => array_unique($g['transfer_ids']),
                        'saving_ids'   => array_unique($g['saving_ids']),
                    ];
                }
            }
        }

        return view('savings.index', compact(
            'period', 'periods', 'savings', 'rows',
            'totalIn', 'totalOut', 'balance', 'sortMode', 'perDoData'
        ));
    }

    /** Input manual tabungan masuk/keluar */
    public function store(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id',
            'entry_date' => 'required|date',
            'type' => 'required|in:in,out',
            'amount' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $period = Period::findOrFail($request->period_id);
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        Saving::create([
            'period_id' => $request->period_id,
            'account_transfer_id' => null, // manual entry
            'entry_date' => $request->entry_date,
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Tabungan disimpan.');
    }

    public function destroy(Saving $saving)
    {
        // Jangan hapus saving yang terhubung ke transfer (hapus via transfer)
        if ($saving->account_transfer_id) {
            return back()->with('error', 'Hapus tabungan ini melalui halaman Transfer (hapus transfer-nya).');
        }

        $periodId = $saving->period_id;
        $saving->delete();
        return redirect()
            ->route('savings.index', ['period_id' => $periodId])
            ->with('success', 'Tabungan dihapus.');
    }
}