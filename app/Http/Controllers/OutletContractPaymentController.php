<?php

namespace App\Http\Controllers;

use App\Models\OutletContractPayment;
use App\Models\Period;
use Illuminate\Http\Request;

class OutletContractPaymentController extends Controller
{
    public function update(Request $request, OutletContractPayment $payment)
    {
        $request->validate([
            'paid_amount' => 'required|integer|min:0',
            'paid_date'   => 'required|date',
            'notes'       => 'nullable|string',
        ]);

        $payment->update([
            'paid_amount' => $request->paid_amount,
            'paid_date'   => $request->paid_date,
            'status'      => $request->paid_amount >= $payment->calculated_amount ? 'paid' : 'unpaid',
            'notes'       => $request->notes,
        ]);

        return back()->with('success', 'Status kontrak diupdate.');
    }

    public function recalc(Request $request)
    {
        $period = Period::findOrFail($request->period_id);
        foreach (\App\Models\Outlet::where('contract_type','!=','none')->where('is_active',true)->get() as $outlet) {
            $amount = $outlet->calculateContractAmount($period);
            OutletContractPayment::updateOrCreate(
                ['period_id' => $period->id, 'outlet_id' => $outlet->id],
                ['calculated_amount' => $amount]
            );
        }
        return back()->with('success', 'Nilai kontrak direcalculate.');
    }
}
