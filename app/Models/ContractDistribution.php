<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\Outlet;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDistribution extends Model
{
    protected $fillable = [
        'period_id','customer_id','outlet_id',
        'total_qty','price_per_unit',
        'tagihan_distribusi','total_setoran',
        'qty_ditinggalkan','nilai_ditinggalkan',
        'tagihan_kontrak',
        'bayar_kontrak_tunai',
        'saldo_bersih','hutang_ke_customer',
        'sudah_diselesaikan','nominal_diselesaikan',
        'catatan_distribusi','catatan_kontrak','catatan_khusus',
        'is_cutoff','cutoff_date',
    ];

    protected $casts = [
        'cutoff_date'        => 'date',
        'is_cutoff'          => 'boolean',
        'hutang_ke_customer' => 'boolean',
        'sudah_diselesaikan' => 'boolean',
    ];

    public function period(): BelongsTo   { return $this->belongsTo(Period::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function outlet(): BelongsTo   { return $this->belongsTo(Outlet::class); }

    // ── LOGIKA ANGGA (flat_monthly) ────────────────────────────────────────
    // Angga "meninggalkan" sejumlah tabung yang tidak ditagih
    // → nilainya langsung offset ke kontrak flat 600rb
    //
    // Contoh: 33 tab × 18.000 = 594.000 → offset kontrak 600rb → sisa kontrak 6.000
    // Tagihan distribusi bersih = tagihan_distribusi - nilai_ditinggalkan
    // Sisa kontrak = 600.000 - nilai_ditinggalkan - bayar_kontrak_tunai

    public function offsetDariDistribusiAngga(): int
    {
        // Berapa nilai "tabung ditinggalkan" yang bisa menutup kontrak
        return min($this->nilai_ditinggalkan, $this->tagihan_kontrak);
    }

    public function sisaKontrakAngga(): int
    {
        return max(0, $this->tagihan_kontrak - $this->offsetDariDistribusiAngga() - $this->bayar_kontrak_tunai);
    }

    public function tagihanDistribusiBersihAngga(): int
    {
        // Yang seharusnya dibayar Angga dari distribusi (sudah dikurangi yang ditinggalkan)
        return max(0, $this->tagihan_distribusi - $this->nilai_ditinggalkan);
    }

    public function piutangDistribusiAngga(): int
    {
        return max(0, $this->tagihanDistribusiBersihAngga() - $this->total_setoran);
    }

    // ── LOGIKA SUKMEDI (per_do) ────────────────────────────────────────────
    // Sukmedi setor seadanya sepanjang bulan.
    // Kalkulasi akhir bulan:
    //   piutang_distribusi = tagihan_distribusi - total_setoran (belum dibayar ke kita)
    //   hak_kontrak        = tagihan_kontrak (DO qty × 1.000 = hak Sukmedi dari kita)
    //   saldo_bersih       = hak_kontrak - piutang_distribusi
    //     > 0: hak Sukmedi > piutangnya → kita bayar ke Sukmedi
    //     < 0: piutang Sukmedi > haknya  → Sukmedi masih kurang
    //
    // Contoh: 50 tab, bayar 828.000 dari 900.000 → piutang 72.000
    //   DO 170 tab → hak kontrak 170.000
    //   saldo = 170.000 - 72.000 = +98.000 → kita bayar ke Sukmedi

    public function piutangDistribusiSukmedi(): int
    {
        // Berapa yang belum dibayar Sukmedi dari distribusi gas
        return max(0, $this->tagihan_distribusi - $this->total_setoran);
    }

    public function hakKontrakSukmedi(): int
    {
        // Berapa yang berhak diterima Sukmedi dari kontrak DO
        return $this->tagihan_kontrak;
    }

    public function saldoSetoran(): int
    {
        // Positif = kita bayar ke Sukmedi
        // Negatif = Sukmedi masih kurang
        return $this->hakKontrakSukmedi() - $this->piutangDistribusiSukmedi();
    }

    // ── HELPER UMUM ────────────────────────────────────────────────────────

    public function sisaBelumDiselesaikan(): int
    {
        if ($this->sudah_diselesaikan) return 0;
        return max(0, abs($this->saldo_bersih) - $this->nominal_diselesaikan);
    }

    public function contractType(): string
    {
        return $this->outlet?->contract_type ?? 'none';
    }

    public function isFlat(): bool   { return $this->contractType() === 'flat_monthly'; }
    public function isPerDo(): bool  { return $this->contractType() === 'per_do'; }

    // ── SYNC DARI DISTRIBUSI HARIAN ────────────────────────────────────────
    public static function syncFromDistributions(Period $period, Customer $customer): self
    {
        $outlet = $customer->outlet;

        $dists = Distribution::where('period_id', $period->id)
            ->where('customer_id', $customer->id)
            ->get();

        $doQty = DeliveryOrder::where('period_id',$period->id)
            ->where('outlet_id',$outlet->id)
            ->where(fn($q) => $q->whereNull('notes')->orWhere('notes','not like','%Carry-over%'))
            ->sum('qty');

        $totalQty     = $dists->sum('qty');
        $tagihanDist  = $dists->sum(fn($d) => $d->qty * $d->price_per_unit);
        $totalSetoran = $dists->sum('paid_amount');
        $priceUnit    = $dists->first()?->price_per_unit ?? 18000;

        if ($outlet?->contract_type == 'flat_monthly') {
        $tagihanKontrak = $outlet->calculateContractAmount($period);
        } elseif ($outlet?->contract_type == 'per_do') {
            $tagihanKontrak = $doQty ? $doQty * 1000 : 0;
        } else {
            $tagihanKontrak = 0; // default kalau bukan 1 atau 4
        }

        $cd = self::firstOrNew([
            'period_id'   => $period->id,
            'customer_id' => $customer->id,
        ]);

        // Selalu update field otomatis dari distribusi
        $cd->outlet_id          = $customer->outlet_id;
        $cd->total_qty          = $totalQty;
        $cd->price_per_unit     = $priceUnit;
        $cd->tagihan_distribusi = $tagihanDist;
        $cd->total_setoran      = $totalSetoran;
        $cd->tagihan_kontrak    = $tagihanKontrak;

        // Isi default hanya untuk record baru
        if (!$cd->exists) {
            $cd->qty_ditinggalkan     = 0;
            $cd->nilai_ditinggalkan   = 0;
            $cd->bayar_kontrak_tunai  = 0;
            $cd->saldo_bersih         = 0;
            $cd->hutang_ke_customer   = false;
            $cd->sudah_diselesaikan   = false;
            $cd->nominal_diselesaikan = 0;
        }

        $cd->save();
        $cd->recalcSaldo();

        return $cd->fresh();
    }

    /** Hitung ulang saldo_bersih dan hutang_ke_customer */
    public function recalcSaldo(): void
    {
        $saldo = 0;
        $hutang = false;

        if ($this->isFlat()) {
            // Angga
            $offset        = $this->offsetDariDistribusiAngga();
            $sisaKontrak   = max(0, $this->tagihan_kontrak - $offset - $this->bayar_kontrak_tunai);
            $tagihanBersih = $this->tagihanDistribusiBersihAngga();
            // Total yang masih harus dibayar Angga = distribusi bersih + sisa kontrak
            $totalKewajiban = $tagihanBersih + $sisaKontrak;
            $saldo  = $this->total_setoran - $totalKewajiban;
            $hutang = $saldo > 0;

        } elseif ($this->isPerDo()) {
            // Sukmedi: hak kontrak - piutang distribusi
            // hak kontrak  = DO qty × 1000
            // piutang dist = tagihan distribusi - setoran yang masuk
            $saldo  = $this->hakKontrakSukmedi() - $this->piutangDistribusiSukmedi();
            $hutang = $saldo > 0; // positif = kita bayar ke Sukmedi
        }

        $this->updateQuietly([
            'saldo_bersih'       => $saldo,
            'hutang_ke_customer' => $hutang,
        ]);
    }
}
