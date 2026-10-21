<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourierResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OutletResource;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Outlet;
use Illuminate\Http\Request;

class MasterController extends Controller
{
    public function index()
    {
        return $this->ok([
            'outlets'   => OutletResource::collection(Outlet::orderBy('name')->get()),
            'customers' => CustomerResource::collection(Customer::with('outlet')->orderBy('name')->get()),
            'couriers'  => CourierResource::collection(Courier::orderBy('name')->get()),
        ]);
    }

    public function storeOutlet(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'contract_type' => 'required|in:none,per_do,flat_monthly',
            'contract_rate' => 'required|integer|min:0',
        ]);

        return $this->ok(new OutletResource(Outlet::create($data)), 201);
    }

    public function updateOutlet(Request $request, Outlet $outlet)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'contract_type' => 'required|in:none,per_do,flat_monthly',
            'contract_rate' => 'required|integer|min:0',
            'is_active'     => 'boolean',
        ]);

        $outlet->update($data);

        return $this->ok(new OutletResource($outlet->fresh()));
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'type'      => 'required|in:regular,contract',
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);

        return $this->ok(new CustomerResource(Customer::create($data + ['is_active' => true])), 201);
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'type'      => 'required|in:regular,contract',
            'outlet_id' => 'nullable|exists:outlets,id',
            'is_active' => 'boolean',
        ]);

        $customer->update($data);

        return $this->ok(new CustomerResource($customer->fresh()->load('outlet')));
    }

    public function storeCourier(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'wage_per_unit' => 'required|integer|min:0',
        ]);

        return $this->ok(new CourierResource(Courier::create($data + ['is_active' => true])), 201);
    }

    public function updateCourier(Request $request, Courier $courier)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'wage_per_unit' => 'required|integer|min:0',
            'is_active'     => 'boolean',
        ]);

        $courier->update($data);

        return $this->ok(new CourierResource($courier->fresh()));
    }
}
