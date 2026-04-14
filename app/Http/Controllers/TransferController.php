<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\Courier;
use App\Models\CourierDeposit;
use App\Models\DeliveryOrder;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();
        $couriers = Courier::where('is_active', true)->get();

        $deposits = CourierDeposit::with('courier')->where('period_id', $period->id)->orderBy('deposit_date')->get();

        $transfers = AccountTransfer::with('deliveryOrders.outlet')->where('period_id', $period->id)->orderBy('transfer_date')->get();

        // Riwayat mutasi penampung
        $allEvents = collect();
        foreach ($deposits as $d) {
            $allEvents->push(['date' => $d->deposit_date, 'type' => 'in', 'amount' => $d->amount, 'desc' => 'Setoran Kurir (' . $d->courier->name . ')']);
        }
        foreach ($transfers as $t) {
            $allEvents->push(['date' => $t->transfer_date, 'type' => 'out', 'amount' => $t->amount, 'desc' => 'Transfer ke Rek Utama']);
        }
        $allEvents = $allEvents->sortBy('date');

        $runningBalance = $period->opening_penampung;
        $balanceRows = [];
        foreach ($allEvents as $ev) {
            $runningBalance += $ev['type'] === 'in' ? $ev['amount'] : -$ev['amount'];
            $balanceRows[] = $ev + ['balance' => $runningBalance];
        }

        // ── PIUTANG DO ─────────────────────────────────────────────────
        // DO bulan ini belum lunas (carry-over didahulukan)
        $unpaidDOs = DeliveryOrder::with('outlet')
            ->where('period_id', $period->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderByRaw("CASE WHEN notes LIKE '%Carry-over%' THEN 0 ELSE 1 END")
            ->orderBy('do_date')
            ->get();

        // DO dari periode sebelumnya yang masih belum lunas
        $prevUnpaidDOs = DeliveryOrder::with(['outlet', 'period'])
            ->whereHas('period', fn($q) => $q->where('id', '<', $period->id))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('do_date')
            ->get();

        // Gabungkan: carry-over & prev dulu, baru bulan ini (urutan pelunasan)
        $allUnpaidDOs = $prevUnpaidDOs->concat($unpaidDOs->filter(fn($d) => str_contains($d->notes ?? '', 'Carry-over')))->concat($unpaidDOs->filter(fn($d) => !str_contains($d->notes ?? '', 'Carry-over')));

        $totalPiutangDO = $allUnpaidDOs->sum(fn($d) => $d->remainingAmount());

        $totalDeposited = $deposits->sum('amount');
        $totalTransferred = $transfers->sum('amount');
        $penampungNow = $period->opening_penampung + $totalDeposited - $totalTransferred;

        $totalNominal = $deposits->sum(fn($d) => $d->amount + $d->admin_fee);
        $totalAdmin = $deposits->sum('admin_fee');
        $totalBersih = $deposits->sum('amount');

        $totalTransferAmount = $transfers->sum('amount');
        $totalTransferQty = $transfers->sum('do_equivalent_qty');
        $totalSurplus = $transfers->sum('surplus');
        $daysInMonth = \Carbon\Carbon::create($period->year, $period->month)->daysInMonth;

        return view('transfer.index', compact('period', 'periods', 'couriers', 'deposits', 'transfers', 'balanceRows', 'unpaidDOs', 'prevUnpaidDOs', 'allUnpaidDOs', 'totalPiutangDO', 'totalDeposited', 'totalTransferred', 'penampungNow', 'totalNominal', 'totalAdmin', 'totalBersih', 'totalTransferAmount', 'totalTransferQty', 'totalSurplus', 'daysInMonth'));
    }

    public function storeDeposit(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id',
            'courier_id' => 'required|exists:couriers,id',
            'deposit_date' => 'required|date',
            'amount' => 'required|integer|min:1',
            'admin_fee' => 'nullable|integer|min:0',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $period = Period::findOrFail($request->period_id);
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        $adminFee = (int) ($request->admin_fee ?? 0);

        CourierDeposit::create([
            'period_id' => $request->period_id,
            'courier_id' => $request->courier_id,
            'deposit_date' => $request->deposit_date,
            'amount' => $request->amount,
            'admin_fee' => $adminFee,
            'reference_no' => $request->reference_no,
            'notes' => $request->notes,
        ]);

        return back()->with('success', 'Setoran kurir disimpan.');
    }

    /**
     * Transfer penampung → rek utama.
     * Alokasi ke DO otomatis berdasarkan urutan (terlama dulu),
     * ATAU bisa manual override per-DO.
     */
    public function storeTransfer(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id',
            'transfer_date' => 'required|date',
            'amount' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'alloc_mode' => 'required|in:auto,manual',
            // Manual: array alokasi per DO
            'do_allocations' => 'nullable|array',
            'do_allocations.*.do_id' => 'required_if:alloc_mode,manual|exists:delivery_orders,id',
            'do_allocations.*.amount' => 'required_if:alloc_mode,manual|integer|min:1',
        ]);

        $period = Period::findOrFail($request->period_id);
        abort_if($period->status === 'closed', 422, 'Periode sudah ditutup.');

        DB::transaction(function () use ($request) {
            $amount = (int) $request->amount;
            $remaining = $amount; // sisa nominal yang belum dialokasikan
            $doEquivQty = 0;
            $surplus = 0;

            $transfer = AccountTransfer::create([
                'period_id' => $request->period_id,
                'transfer_date' => $request->transfer_date,
                'amount' => $amount,
                'do_equivalent_qty' => 0,
                'surplus' => 0,
                'notes' => $request->notes,
            ]);

            if ($request->alloc_mode === 'auto') {
                // ── AUTO: alokasikan ke semua DO belum lunas, terlama dulu ──
                // Urutan: prev period dulu, lalu carry-over, lalu DO bulan ini
                $periodId = (int) $request->period_id;

                $doList = DeliveryOrder::with('outlet')
                    ->whereHas('period', fn($q) => $q->where('id', '<=', $periodId))
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->orderByRaw("CASE WHEN notes LIKE '%Carry-over%' THEN 0 ELSE 1 END")
                    ->orderBy('do_date')
                    ->orderBy('id')
                    ->get();

                foreach ($doList as $do) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $allocAmount = min($remaining, $do->remainingAmount());
                    if ($allocAmount <= 0) {
                        continue;
                    }

                    $transfer->deliveryOrders()->attach($do->id, ['amount_allocated' => $allocAmount]);
                    $remaining -= $allocAmount;
                    $doEquivQty += intdiv($allocAmount, $do->price_per_unit);
                    $do->recalcPayment();
                }

                $surplus = $remaining; // sisa setelah semua DO terlunasi = surplus/tabungan
            } else {
                // ── MANUAL: alokasi per-DO sesuai input user ──
                foreach ($request->do_allocations ?? [] as $alloc) {
                    $do = DeliveryOrder::find($alloc['do_id']);
                    if (!$do) {
                        continue;
                    }

                    $allocAmount = min((int) $alloc['amount'], $do->remainingAmount());
                    if ($allocAmount <= 0) {
                        continue;
                    }

                    $transfer->deliveryOrders()->attach($do->id, ['amount_allocated' => $allocAmount]);
                    $remaining -= $allocAmount;
                    $doEquivQty += intdiv($allocAmount, $do->price_per_unit);
                    $do->recalcPayment();
                }

                $surplus = max(0, $remaining);
            }

            $transfer->update([
                'do_equivalent_qty' => $doEquivQty,
                'surplus' => $surplus,
            ]);

            // Jika ada surplus, otomatis catat ke tabel savings
            if ($surplus > 0) {
                \App\Models\Saving::create([
                    'period_id' => $request->period_id,
                    'account_transfer_id' => $transfer->id,
                    'entry_date' => $request->transfer_date,
                    'type' => 'in',
                    'amount' => $surplus,
                    'description' => 'Surplus transfer ' . $request->transfer_date . ($request->notes ? ' — ' . $request->notes : ''),
                ]);
            }
        });

        return back()->with('success', 'Transfer disimpan & status DO otomatis diperbarui.');
    }

    public function destroyDeposit(CourierDeposit $deposit)
    {
        $periodId = $deposit->period_id;
        $deposit->delete();
        return redirect()
            ->route('transfer.index', ['period_id' => $periodId])
            ->with('success', 'Setoran dihapus.');
    }

    public function destroyTransfer(AccountTransfer $transfer)
    {
        $periodId = $transfer->period_id;
        DB::transaction(function () use ($transfer) {
            $doIds = $transfer->deliveryOrders()->pluck('delivery_orders.id');
            $transfer->deliveryOrders()->detach();
            // Hapus saving terkait transfer ini
            \App\Models\Saving::where('account_transfer_id', $transfer->id)->delete();
            $transfer->delete();
            foreach (DeliveryOrder::whereIn('id', $doIds)->get() as $do) {
                $do->recalcPayment();
            }
        });
        return redirect()
            ->route('transfer.index', ['period_id' => $periodId])
            ->with('success', 'Transfer dihapus & status DO dikembalikan.');
    }
}
