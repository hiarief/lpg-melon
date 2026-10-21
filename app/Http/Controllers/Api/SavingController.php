<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SavingResource;
use App\Models\Saving;
use Illuminate\Http\Request;

class SavingController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);
        $sort = in_array($request->query('sort'), ['do', 'per_do']) ? $request->query('sort') : 'transfer';

        $savings = Saving::with('accountTransfer.deliveryOrders')->where('period_id', $period->id)
            ->orderBy('entry_date')->orderBy('id')->get();

        $savings = $savings->sortBy(function ($s) use ($sort) {
            if ($sort === 'do' || $sort === 'per_do') {
                if ($s->accountTransfer && $s->accountTransfer->deliveryOrders->isNotEmpty()) {
                    return $s->accountTransfer->deliveryOrders->min('do_date');
                }
            }
            return $s->accountTransfer?->transfer_date ?? $s->entry_date;
        })->values();

        $totalIn  = (int) $savings->where('type', 'in')->sum('amount');
        $totalOut = (int) $savings->where('type', 'out')->sum('amount');
        $balance  = (int) ($period->opening_surplus + $totalIn - $totalOut);

        // Running balance per baris
        $running = (int) $period->opening_surplus;
        $rows = $savings->map(function ($s) use (&$running) {
            $running += $s->type === 'in' ? (int) $s->amount : -(int) $s->amount;

            return [
                'saving'       => new SavingResource($s),
                'balance'      => $running,
                'transfer_date' => $s->accountTransfer?->transfer_date?->toDateString(),
                'earliest_do'   => $s->accountTransfer && $s->accountTransfer->deliveryOrders->isNotEmpty()
                    ? optional($s->accountTransfer->deliveryOrders->sortBy('do_date')->first())->do_date?->toDateString()
                    : null,
            ];
        })->values()->all();

        // Tab "Mutasi Tabungan per Tanggal DO"
        $perDo = [];
        foreach ($savings->where('type', 'in') as $s) {
            $t = $s->accountTransfer;
            if (! $t) continue;
            foreach ($t->deliveryOrders as $do) {
                $key = $do->do_date?->toDateString() . '|' . $do->outlet_id;
                if (! isset($perDo[$key])) {
                    $perDo[$key] = [
                        'do_date' => $do->do_date?->toDateString(),
                        'outlet_id' => $do->outlet_id,
                        'outlet_name' => $do->outlet?->name,
                        'qty' => 0, 'value' => 0, 'surplus' => 0,
                    ];
                }
                $perDo[$key]['qty']     += (int) $do->qty;
                $perDo[$key]['value']   += (int) $do->totalValue();
                $perDo[$key]['surplus'] += (int) $s->amount;
            }
        }
        ksort($perDo);

        return $this->ok([
            'period_id'   => $period->id,
            'sort'        => $sort,
            'rows'        => $rows,
            'savings'     => SavingResource::collection($savings),
            'per_do'      => array_values($perDo),
            'opening'     => (int) $period->opening_surplus,
            'total_in'    => $totalIn,
            'total_out'   => $totalOut,
            'balance'     => $balance,
        ]);
    }

    public function store(Request $request)
    {
        $period = $this->resolvePeriod($request);

        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'entry_date'   => 'required|date',
            'type'         => 'required|in:in,out',
            'amount'       => 'required|integer|min:1',
            'description'  => 'nullable|string|max:255',
        ]);

        return $this->ok(new SavingResource(Saving::create($data + ['period_id' => $period->id, 'account_transfer_id' => null])), 201);
    }

    public function destroy(Saving $saving)
    {
        abort_if($saving->account_transfer_id, 422, 'Hapus tabungan ini melalui halaman Transfer (hapus transfer-nya).');

        $saving->delete();

        return $this->ok(['deleted' => true]);
    }
}
