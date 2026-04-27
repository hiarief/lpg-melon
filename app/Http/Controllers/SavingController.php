<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Saving;
use Illuminate\Http\Request;

class SavingController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        // Eager load accountTransfer beserta deliveryOrders-nya
        $savings = Saving::with([
            'accountTransfer.deliveryOrders',
        ])
            ->where('period_id', $period->id)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // Sort: yang punya DO → urutkan by tanggal DO terlama; manual → by entry_date
        $savings = $savings->sortBy(function ($s) {
            if ($s->accountTransfer && $s->accountTransfer->deliveryOrders->isNotEmpty()) {
                return $s->accountTransfer->deliveryOrders->min('do_date');
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

            $rows[] = [
                'saving'       => $s,
                'balance'      => $running,
                'transfer_date'=> $transferDate,
                'do_list'      => $doList,
                'earliest_do'  => $earliestDo,
            ];
        }

        return view('savings.index', compact(
            'period', 'periods', 'savings', 'rows',
            'totalIn', 'totalOut', 'balance'
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