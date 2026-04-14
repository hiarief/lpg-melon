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

        $savings = Saving::with('accountTransfer')->where('period_id', $period->id)->orderBy('entry_date')->orderBy('id')->get();

        $totalIn = $savings->where('type', 'in')->sum('amount');
        $totalOut = $savings->where('type', 'out')->sum('amount');
        $balance = $period->opening_surplus + $totalIn - $totalOut;

        // Running balance per row
        $running = $period->opening_surplus;
        $rows = [];
        foreach ($savings as $s) {
            $running += $s->type === 'in' ? $s->amount : -$s->amount;
            $rows[] = ['saving' => $s, 'balance' => $running];
        }

        return view('savings.index', compact('period', 'periods', 'savings', 'rows', 'totalIn', 'totalOut', 'balance'));
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