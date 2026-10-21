<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashflowController;
use App\Http\Controllers\Api\ContractDistributionController;
use App\Http\Controllers\Api\DeliveryOrderController;
use App\Http\Controllers\Api\DistributionController;
use App\Http\Controllers\Api\ExternalDebtController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\SavingController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — backend untuk aplikasi Flutter Android
|--------------------------------------------------------------------------
|
| Semua endpoint di sini mengembalikan JSON:
|   { "success": true, "data": ... }   atau   { "success": false, "error": "..." }
|
| Autentikasi memakai token API (Laravel Sanctum) via header
|   Authorization: Bearer <token>
| Token didapat dari POST /api/login.
|
*/

// Catatan: `api:` di bootstrap/app.php sudah menambahkan prefix `api`,
// jadi di sini kita TIDAK memakai prefix('api') lagi.

// ─── AUTH ─────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
Route::post('/password', [AuthController::class, 'changePassword'])->name('api.password');

    // ─── SEMUA ENDPOINT DI BAWAH INI BUTUH TOKEN ──────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AuthController::class, 'me'])->name('api.me');
        // ─── PERIODE & RINGKASAN ────────────────────────────────────────────
        Route::get('/periods', [PeriodController::class, 'index'])->name('api.periods.index');
        Route::post('/periods', [PeriodController::class, 'store'])->name('api.periods.store');
        Route::put('/periods/{period}', [PeriodController::class, 'update'])->name('api.periods.update');
        Route::post('/periods/{period}/close', [PeriodController::class, 'close'])->name('api.periods.close');
        Route::delete('/periods/{period}', [PeriodController::class, 'destroy'])->name('api.periods.destroy');

        Route::get('/summary', [SummaryController::class, 'show'])->name('api.summary');
        Route::get('/summary/kpi', [SummaryController::class, 'kpi'])->name('api.summary.kpi');

        // ─── MASTER DATA ────────────────────────────────────────────────────
        Route::get('/master', [MasterController::class, 'index'])->name('api.master.index');
        Route::post('/master/outlets', [MasterController::class, 'storeOutlet'])->name('api.master.outlets.store');
        Route::put('/master/outlets/{outlet}', [MasterController::class, 'updateOutlet'])->name('api.master.outlets.update');
        Route::post('/master/customers', [MasterController::class, 'storeCustomer'])->name('api.master.customers.store');
        Route::put('/master/customers/{customer}', [MasterController::class, 'updateCustomer'])->name('api.master.customers.update');
        Route::post('/master/couriers', [MasterController::class, 'storeCourier'])->name('api.master.couriers.store');
        Route::put('/master/couriers/{courier}', [MasterController::class, 'updateCourier'])->name('api.master.couriers.update');

        // ─── DELIVERY ORDER ─────────────────────────────────────────────────
        Route::get('/do', [DeliveryOrderController::class, 'index'])->name('api.do.index');
        Route::post('/do', [DeliveryOrderController::class, 'store'])->name('api.do.store');
        Route::put('/do/{do}', [DeliveryOrderController::class, 'update'])->name('api.do.update');
        Route::delete('/do/{do}', [DeliveryOrderController::class, 'destroy'])->name('api.do.destroy');

        // ─── DISTRIBUSI ─────────────────────────────────────────────────────
        Route::get('/distributions', [DistributionController::class, 'index'])->name('api.distributions.index');
        Route::post('/distributions', [DistributionController::class, 'store'])->name('api.distributions.store');
        Route::post('/distributions/bulk', [DistributionController::class, 'bulkStore'])->name('api.distributions.bulk');
        Route::put('/distributions/{distribution}', [DistributionController::class, 'update'])->name('api.distributions.update');
        Route::post('/distributions/{distribution}/payment', [DistributionController::class, 'recordPayment'])->name('api.distributions.payment');
        Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy'])->name('api.distributions.destroy');

        // ─── CASHFLOW HARIAN ────────────────────────────────────────────────
        Route::get('/cashflow', [CashflowController::class, 'index'])->name('api.cashflow.index');
        Route::post('/cashflow', [CashflowController::class, 'store'])->name('api.cashflow.store');
        Route::post('/cashflow/bulk', [CashflowController::class, 'bulkStore'])->name('api.cashflow.bulk');
        Route::put('/cashflow/{expense}', [CashflowController::class, 'update'])->name('api.cashflow.update');
        Route::delete('/cashflow/{expense}', [CashflowController::class, 'destroy'])->name('api.cashflow.destroy');

        // ─── TRANSFER & SETORAN KURIR ───────────────────────────────────────
        Route::get('/transfer', [TransferController::class, 'index'])->name('api.transfer.index');
        Route::post('/transfer/deposit', [TransferController::class, 'storeDeposit'])->name('api.transfer.deposit.store');
        Route::delete('/transfer/deposit/{deposit}', [TransferController::class, 'destroyDeposit'])->name('api.transfer.deposit.destroy');
        Route::post('/transfer/account', [TransferController::class, 'storeTransfer'])->name('api.transfer.account.store');
        Route::delete('/transfer/account/{transfer}', [TransferController::class, 'destroyTransfer'])->name('api.transfer.account.destroy');

        // ─── TABUNGAN / SURPLUS ─────────────────────────────────────────────
        Route::get('/savings', [SavingController::class, 'index'])->name('api.savings.index');
        Route::post('/savings', [SavingController::class, 'store'])->name('api.savings.store');
        Route::delete('/savings/{saving}', [SavingController::class, 'destroy'])->name('api.savings.destroy');

        // ─── PIUTANG EXTERNAL ───────────────────────────────────────────────
        Route::get('/external-debt', [ExternalDebtController::class, 'index'])->name('api.external-debt.index');
        Route::post('/external-debt', [ExternalDebtController::class, 'store'])->name('api.external-debt.store');
        Route::delete('/external-debt/{externalDebt}', [ExternalDebtController::class, 'destroy'])->name('api.external-debt.destroy');

        // ─── DISTRIBUSI KONTRAK ─────────────────────────────────────────────
        Route::get('/contract-distributions', [ContractDistributionController::class, 'index'])->name('api.contract-dist.index');
        Route::post('/contract-distributions/sync', [ContractDistributionController::class, 'sync'])->name('api.contract-dist.sync');
        Route::put('/contract-distributions/{contractDistribution}', [ContractDistributionController::class, 'update'])->name('api.contract-dist.update');
        Route::post('/contract-distributions/{contractDistribution}/selesaikan', [ContractDistributionController::class, 'selesaikan'])->name('api.contract-dist.selesaikan');
        Route::post('/contract-distributions/{contractDistribution}/reset', [ContractDistributionController::class, 'resetSelesaikan'])->name('api.contract-dist.reset');
        Route::post('/contract-distributions/{contractDistribution}/cutoff', [ContractDistributionController::class, 'cutoff'])->name('api.contract-dist.cutoff');
    });

