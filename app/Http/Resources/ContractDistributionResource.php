<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractDistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'period_id'         => $this->period_id,
            'customer_id'       => $this->customer_id,
            'customer_name'     => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'outlet_id'         => $this->outlet_id,
            'outlet_name'       => $this->whenLoaded('outlet', fn () => $this->outlet?->name),
            'total_qty'         => (int) $this->total_qty,
            'price_per_unit'    => (int) $this->price_per_unit,
            'tagihan_distribusi' => (int) $this->tagihan_distribusi,
            'total_setoran'     => (int) $this->total_setoran,
            'qty_ditinggalkan'  => (int) $this->qty_ditinggalkan,
            'nilai_ditinggalkan' => (int) $this->nilai_ditinggalkan,
            'bayar_kontrak_tunai' => (int) $this->bayar_kontrak_tunai,
            'tagihan_kontrak'   => (int) $this->tagihan_kontrak,
            'saldo_bersih'      => (int) $this->saldo_bersih,
            'hutang_ke_customer' => (bool) $this->hutang_ke_customer,
            'sudah_diselesaikan' => (bool) $this->sudah_diselesaikan,
            'nominal_diselesaikan' => (int) $this->nominal_diselesaikan,
            'is_cutoff'         => (bool) $this->is_cutoff,
            'cutoff_date'       => $this->cutoff_date?->toDateString(),
            'contract_type'     => $this->contractType(),
            'catatan_distribusi' => $this->catatan_distribusi,
            'catatan_kontrak'   => $this->catatan_kontrak,
            'catatan_khusus'    => $this->catatan_khusus,

            // ── TURUNAN — dihitung di server, klien hanya merender ───────
            'is_flat'            => $this->isFlat(),
            'is_per_do'          => $this->isPerDo(),
            'tagihan_distribusi_bersih' => $this->tagihanDistribusiBersih(),
            'piutang_distribusi'        => $this->piutangDistribusi(),
            'offset_dari_distribusi'    => $this->isFlat() ? $this->offsetDariDistribusiAngga() : 0,
            'sisa_kontrak'              => $this->sisaKontrakFlat(),
            'sisa_belum_diselesaikan'   => $this->sisaBelumDiselesaikan(),

            // Lampiran dari controller, bukan kolom tabel.
            'do_qty' => (int) ($this->doQty ?? 0),

            'daily_distributions' => $this->dailyDistributionsMap(),
        ];
    }

    /**
     * Timeline distribusi harian customer ini.
     *
     * Controller melampirkan `dailyDistributions` (koleksi Distribution).
     * Bila tidak dilampirkan → list kosong, bukan error.
     */
    private function dailyDistributionsMap(): array
    {
        $items = $this->dailyDistributions;

        if ($items === null) {
            return [];
        }

        return $items
            ->map(function ($d) {
                $nilai = (int) $d->qty * (int) $d->price_per_unit;

                return [
                    'id'               => $d->id,
                    'dist_date'        => $d->dist_date?->toDateString(),
                    'qty'              => (int) $d->qty,
                    'price_per_unit'   => (int) $d->price_per_unit,
                    'total_value'      => $nilai,
                    'paid_amount'      => (int) $d->paid_amount,
                    'remaining_amount' => max(0, $nilai - (int) $d->paid_amount),
                    'payment_status'   => $d->payment_status,
                    'notes'            => $d->contract_note ?? $d->notes,
                ];
            })
            ->values()
            ->all();
    }
}
