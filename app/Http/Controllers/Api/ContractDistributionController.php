<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractDistributionResource;
use App\Models\ContractDistribution;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\Period;
use Illuminate\Http\Request;

class ContractDistributionController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $rows = ContractDistribution::with(['customer', 'outlet'])
            ->where('period_id', $period->id)->get();

        $this->attachDetails($rows, $period);

        return $this->ok([
            'period_id' => $period->id,
            'rows'      => ContractDistributionResource::collection($rows),
            'totals'    => [
                'total_qty'          => (int) $rows->sum('total_qty'),
                'tagihan_distribusi' => (int) $rows->sum('tagihan_distribusi'),
                'total_setoran'      => (int) $rows->sum('total_setoran'),
                'tagihan_kontrak'    => (int) $rows->sum('tagihan_kontrak'),
                'saldo_bersih'       => (int) $rows->sum('saldo_bersih'),
            ],
        ]);
    }

    /** Sinkronkan ulang angka otomatis dari distribusi harian. */
    public function sync(Request $request)
    {
        $period = $this->resolvePeriod($request);

        $customers = Customer::with('outlet')->where('type', 'contract')->get();

        $synced = [];
        foreach ($customers as $customer) {
            $cd = ContractDistribution::syncFromDistributions($period, $customer);
            $cd = $cd->load(['customer', 'outlet']);
            $this->attachDetails(collect([$cd]), $period);
            $synced[] = new ContractDistributionResource($cd);
        }

        return $this->ok(['synced' => $synced]);
    }

    /**
     * Lampirkan data turunan yang bukan kolom tabel: timeline distribusi
     * harian dan DO qty (untuk hak kontrak skema per-DO).
     *
     * @param  \Illuminate\Support\Collection<int, ContractDistribution>  $rows
     */
    private function attachDetails($rows, Period $period): void
    {
        $rows->each(function ($cd) use ($period) {
            $cd->dailyDistributions = Distribution::where('period_id', $period->id)
                ->where('customer_id', $cd->customer_id)
                ->orderBy('dist_date')
                ->get();

            $cd->doQty = DeliveryOrder::where('period_id', $period->id)
                ->where('outlet_id', $cd->outlet_id)
                ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
                ->sum('qty');
        });
    }

    public function update(Request $request, ContractDistribution $contractDistribution)
    {
        $data = $request->validate([
            'qty_ditinggalkan'       => 'nullable|integer|min:0',
            'nilai_ditinggalkan'     => 'nullable|integer|min:0',
            'bayar_kontrak_tunai'    => 'nullable|integer|min:0',
            'sudah_diselesaikan'     => 'nullable|boolean',
            'nominal_diselesaikan'   => 'nullable|integer|min:0',
            'catatan_distribusi'     => 'nullable|string',
            'catatan_kontrak'        => 'nullable|string',
            'catatan_khusus'         => 'nullable|string',
        ]);

        $contractDistribution->update($data);
        $contractDistribution->recalcSaldo();

        return $this->ok(new ContractDistributionResource($contractDistribution->fresh(['customer', 'outlet'])));
    }

    public function selesaikan(Request $request, ContractDistribution $contractDistribution)
    {
        $data = $request->validate(['nominal_diselesaikan' => 'required|integer|min:0']);

        $contractDistribution->update([
            'sudah_diselesaikan'   => true,
            'nominal_diselesaikan' => $data['nominal_diselesaikan'],
        ]);

        return $this->ok(new ContractDistributionResource($contractDistribution->fresh(['customer', 'outlet'])));
    }

    public function resetSelesaikan(ContractDistribution $contractDistribution)
    {
        $contractDistribution->update([
            'sudah_diselesaikan'   => false,
            'nominal_diselesaikan' => 0,
        ]);

        return $this->ok(new ContractDistributionResource($contractDistribution->fresh(['customer', 'outlet'])));
    }

    public function cutoff(Request $request, ContractDistribution $contractDistribution)
    {
        $data = $request->validate(['cutoff_date' => 'required|date']);

        $contractDistribution->update([
            'is_cutoff'   => true,
            'cutoff_date' => $data['cutoff_date'],
        ]);

        return $this->ok(new ContractDistributionResource($contractDistribution->fresh(['customer', 'outlet'])));
    }
}
