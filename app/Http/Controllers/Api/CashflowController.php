<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyExpenseResource;
use App\Models\DailyExpense;
use Illuminate\Http\Request;

class CashflowController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $expenses = DailyExpense::where('period_id', $period->id)
            ->orderBy('expense_date')->orderBy('id')->get();

        $byCategory = DailyExpense::where('period_id', $period->id)
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')
            ->pluck('total', 'category')->map(fn ($v) => (int) $v);

        return $this->ok([
            'period_id' => $period->id,
            'expenses'  => DailyExpenseResource::collection($expenses),
            'by_category' => $byCategory,
            'total'     => (int) $expenses->sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'expense_date' => 'required|date',
            'category'     => 'required|in:' . implode(',', array_keys(DailyExpense::$categoryLabels)),
            'description'  => 'nullable|string|max:255',
            'amount'       => 'required|integer|min:1',
            'notes'        => 'nullable|string',
        ]);

        return $this->ok(new DailyExpenseResource(DailyExpense::create($data + ['period_id' => $period->id])), 201);
    }

    public function bulkStore(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $rows = $request->validate([
            'items'             => 'required|array|min:1',
            'items.*.expense_date' => 'required|date',
            'items.*.category'     => 'required|in:' . implode(',', array_keys(DailyExpense::$categoryLabels)),
            'items.*.description'  => 'nullable|string|max:255',
            'items.*.amount'       => 'required|integer|min:1',
            'items.*.notes'        => 'nullable|string',
        ]);

        foreach ($rows['items'] as $item) {
            DailyExpense::create($item + ['period_id' => $period->id]);
        }

        return $this->ok(['created' => count($rows['items'])], 201);
    }

    public function update(Request $request, DailyExpense $expense)
    {
        $data = $request->validate([
            'expense_date' => 'required|date',
            'category'     => 'required|in:' . implode(',', array_keys(DailyExpense::$categoryLabels)),
            'description'  => 'nullable|string|max:255',
            'amount'       => 'required|integer|min:1',
            'notes'        => 'nullable|string',
        ]);

        $expense->update($data);

        return $this->ok(new DailyExpenseResource($expense->fresh()));
    }

    public function destroy(DailyExpense $expense)
    {
        $expense->delete();

        return $this->ok(['deleted' => true]);
    }
}
