<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Customer;
use App\Models\Courier;
use Illuminate\Http\Request;

class MasterController extends Controller
{
    public function index()
    {
        $outlets   = Outlet::withCount('deliveryOrders')->get();
        $customers = Customer::with('outlet')->where('is_active', true)->orderBy('name')->get();
        $couriers  = Courier::all();
        return view('master.index', compact('outlets','customers','couriers'));
    }

    // ─── OUTLET ─────────────────────────────────────────────────────────
    public function storeOutlet(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'contract_type' => 'required|in:none,per_do,flat_monthly',
            'contract_rate' => 'required|integer|min:0',
        ]);
        Outlet::create($request->only('name','contract_type','contract_rate'));
        return back()->with('success', 'Pangkalan disimpan.');
    }

    public function updateOutlet(Request $request, Outlet $outlet)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'contract_type' => 'required|in:none,per_do,flat_monthly',
            'contract_rate' => 'required|integer|min:0',
            'is_active'     => 'boolean',
        ]);
        $outlet->update($request->only('name','contract_type','contract_rate','is_active'));
        return back()->with('success', 'Pangkalan diupdate.');
    }

    // ─── CUSTOMER ────────────────────────────────────────────────────────
    public function storeCustomer(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'type'      => 'required|in:regular,contract',
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);
        Customer::create($request->only('name','type','outlet_id') + ['is_active' => true]);
        return back()->with('success', 'Customer disimpan.');
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'type'      => 'required|in:regular,contract',
            'outlet_id' => 'nullable|exists:outlets,id',
            'is_active' => 'boolean',
        ]);
        $customer->update($request->only('name','type','outlet_id','is_active'));
        return back()->with('success', 'Customer diupdate.');
    }

    // ─── COURIER ─────────────────────────────────────────────────────────
    public function storeCourier(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'wage_per_unit' => 'required|integer|min:0',
        ]);
        Courier::create($request->only('name','wage_per_unit') + ['is_active' => true]);
        return back()->with('success', 'Kurir disimpan.');
    }

    public function updateCourier(Request $request, Courier $courier)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'wage_per_unit' => 'required|integer|min:0',
            'is_active'     => 'boolean',
        ]);
        $courier->update($request->only('name','wage_per_unit','is_active'));
        return back()->with('success', 'Kurir diupdate.');
    }
}
