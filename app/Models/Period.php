<?php

namespace App\Models;

use App\Models\AccountTransfer;
use App\Models\CourierDeposit;
use App\Models\DailyExpense;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\ExternalDebt;
use App\Models\OutletContractPayment;
use App\Models\Saving;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    protected $fillable = ['year', 'month', 'label', 'status', 'opening_stock', 'opening_cash', 'opening_penampung', 'opening_external_debt', 'opening_do_unpaid_qty'];

    protected $casts = ['status' => 'string'];

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }
    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
    public function dailyExpenses(): HasMany
    {
        return $this->hasMany(DailyExpense::class);
    }
    public function courierDeposits(): HasMany
    {
        return $this->hasMany(CourierDeposit::class);
    }
    public function accountTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class);
    }
    public function externalDebts(): HasMany
    {
        return $this->hasMany(ExternalDebt::class);
    }
    public function outletContractPayments(): HasMany
    {
        return $this->hasMany(OutletContractPayment::class);
    }
    public function savings(): HasMany
    {
        return $this->hasMany(Saving::class);
    }

    /** Saldo tabungan bulan ini (opening + surplus masuk - pengambilan) */
    public function totalSavingsBalance(): int
    {
        $in = $this->savings()->where('type', 'in')->sum('amount');
        $out = $this->savings()->where('type', 'out')->sum('amount');
        return $this->opening_surplus + $in - $out;
    }

    /**
     * DO murni bulan ini — TIDAK termasuk carry-over dari bulan lalu.
     * Carry-over hanya piutang lama, bukan stok masuk baru.
     */
    public function totalDoQty(): int
    {
        return $this->deliveryOrders()->where(fn($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))->sum('qty');
    }

    /**
     * DO carry-over saja (piutang bawaan dari bulan lalu).
     * Ini BUKAN stok masuk baru, sudah masuk di opening_stock.
     */
    public function totalCarryoverQty(): int
    {
        return $this->deliveryOrders()->where('notes', 'like', '%Carry-over%')->sum('qty');
    }

    /** Total distribusi bulan ini */
    public function totalDistQty(): int
    {
        return $this->distributions()->sum('qty');
    }

    /** Total nilai penjualan bruto */
    public function totalSales(): int
    {
        // total_value adalah generated column, hitung manual
        $rows = $this->distributions()->get(['qty', 'price_per_unit']);
        return $rows->sum(fn($d) => $d->qty * $d->price_per_unit);
    }

    /** Total setoran kurir ke penampung */
    public function totalCourierDeposits(): int
    {
        return $this->courierDeposits()->sum('net_amount');
    }

    /** Total transfer penampung ke rek utama */
    public function totalTransfers(): int
    {
        return $this->accountTransfers()->sum('amount');
    }

    /** Total surplus dari semua transfer (tabungan di rek utama) */
    public function totalSurplus(): int
    {
        return $this->accountTransfers()->sum('surplus');
    }

    /** Sisa piutang DO yang belum terlunasi (hanya DO non-carry-over bulan ini) */
    public function unpaidDoValue(): int
    {
        return $this->deliveryOrders()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get()
            ->sum(fn($d) => $d->remainingAmount());
    }

    public static function current(): ?self
    {
        return self::where('status', 'open')->latest('id')->first();
    }
}
