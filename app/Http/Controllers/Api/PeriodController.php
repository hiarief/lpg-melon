<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodResource;
use App\Models\DeliveryOrder;
use App\Models\Outlet;
use App\Models\OutletContractPayment;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function index()
    {
        return $this->ok([
            'periods'         => PeriodResource::collection(Period::orderByDesc('year')->orderByDesc('month')->get()),
            'current_period'  => Period::current() ? new PeriodResource(Period::current()) : null,
        ]);
    }

    public function store(Request $request)
    {
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $data = $request->validate([
            'year'                  => 'required|integer|min:2020|max:2100',
            'month'                 => 'required|integer|min:1|max:12',
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

        $period = Period::create([
            'year'                  => $data['year'],
            'month'                 => $data['month'],
            'label'                 => $monthNames[$data['month']] . ' ' . $data['year'],
            'status'                => 'open',
            'opening_stock'         => $data['opening_stock'],
            'opening_cash'          => $data['opening_cash'],
            'opening_penampung'     => $data['opening_penampung'],
            'opening_external_debt' => $data['opening_external_debt'],
            'opening_surplus'       => $data['opening_surplus'] ?? 0,
            'opening_do_unpaid_qty' => collect($data['carryover'] ?? [])->sum('qty'),
        ]);

        // Catat DO carry-over sebagai record nyata
        $lastDayPrevMonth = Carbon::create($data['year'], $data['month'], 1)->subDay()->format('Y-m-d');
        foreach (collect($data['carryover'] ?? [])->filter(fn ($r) => ! empty($r['outlet_id']) && ($r['qty'] ?? 0) > 0) as $row) {
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

        return $this->ok(new PeriodResource($period->fresh()), 201);
    }

    public function update(Request $request, Period $period)
    {
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup, tidak bisa diedit.');

        $data = $request->validate([
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

        $period->update($request->only('opening_stock', 'opening_cash', 'opening_penampung', 'opening_external_debt')
            + ['opening_surplus' => $data['opening_surplus'] ?? 0]);

        return $this->ok(new PeriodResource($period->fresh()));
    }

    public function close(Period $period)
    {
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $period->update(['status' => 'closed']);

        return $this->ok(new PeriodResource($period->fresh()));
    }

    public function destroy(Period $period)
    {
        $latest = Period::orderByDesc('year')->orderByDesc('month')->first();
        if ($latest && $latest->id !== $period->id) {
            return $this->error('Hanya periode paling akhir yang boleh dihapus.', 422);
        }

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

        return $this->ok(['deleted' => true]);
    }
}
