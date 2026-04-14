<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExternalDebt;
use App\Models\Period;
use Illuminate\Http\Request;

class ExternalDebtController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        $debts = ExternalDebt::where('period_id', $period->id)->orderBy('entry_date')->get();

        $netIn = $debts->where('type', 'in')->sum('amount');
        $netOut = $debts->where('type', 'out')->sum('amount');
        $balance = $period->opening_external_debt + $netIn - $netOut;

        return view('external-debt.index', compact('period', 'periods', 'debts', 'netIn', 'netOut', 'balance'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id',
            'entry_date' => 'required|date',
            'type' => 'required|in:in,out',
            'source_name' => 'required|string|max:100',
            'amount' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $period = Period::findOrFail($request->period_id);
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        ExternalDebt::create($request->only('period_id', 'entry_date', 'type', 'source_name', 'amount', 'description'));

        return back()->with('success', 'Piutang external disimpan.');
    }

    public function destroy(ExternalDebt $externalDebt)
    {
        $periodId = $externalDebt->period_id;
        $externalDebt->delete();
        return redirect()
            ->route('external-debt.index', ['period_id' => $periodId])
            ->with('success', 'Data dihapus.');
    }
}