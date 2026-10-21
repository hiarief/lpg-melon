<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExternalDebtResource;
use App\Models\ExternalDebt;
use Illuminate\Http\Request;

class ExternalDebtController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $debts = ExternalDebt::where('period_id', $period->id)->orderBy('entry_date')->get();

        $in  = (int) $debts->where('type', 'in')->sum('amount');
        $out = (int) $debts->where('type', 'out')->sum('amount');

        return $this->ok([
            'period_id' => $period->id,
            'debts'     => ExternalDebtResource::collection($debts),
            'opening'   => (int) $period->opening_external_debt,
            'total_in'  => $in,
            'total_out' => $out,
            'balance'   => (int) ($period->opening_external_debt + $in - $out),
        ]);
    }

    public function store(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'entry_date'   => 'required|date',
            'type'         => 'required|in:in,out',
            'source_name'  => 'required|string|max:100',
            'amount'       => 'required|integer|min:1',
            'description'  => 'nullable|string',
        ]);

        return $this->ok(new ExternalDebtResource(ExternalDebt::create($data + ['period_id' => $period->id])), 201);
    }

    public function destroy(ExternalDebt $externalDebt)
    {
        $externalDebt->delete();

        return $this->ok(['deleted' => true]);
    }
}
