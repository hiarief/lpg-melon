<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\ContractDistributionController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExternalDebtController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\OutletContractPaymentController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\SavingController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

// ─── AUTH (guest only) ───────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// ─── SEMUA ROUTE DILINDUNGI LOGIN ─────────────────────────────────────────
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', function () {
        $period = \App\Models\Period::current();
        if ($period) {
            return redirect()->route('summary.index', ['period_id' => $period->id]);
        }
        return redirect()->route('periods.index');
    })->name('home');

    // Ganti password
    Route::get('/password', [AuthController::class, 'showChangePassword'])->name('password.form');
    Route::post('/password', [AuthController::class, 'changePassword'])->name('password.change');

    // ─── PERIODS ─────────────────────────────────────────────────────────────
    Route::prefix('periods')
        ->name('periods.')
        ->group(function () {
            Route::get('/', [PeriodController::class, 'index'])->name('index');
            Route::get('/create', [PeriodController::class, 'create'])->name('create');
            Route::post('/', [PeriodController::class, 'store'])->name('store');
            Route::get('/{period}', [PeriodController::class, 'show'])->name('show');
            Route::get('/{period}/edit', [PeriodController::class, 'edit'])->name('edit');
            Route::put('/{period}', [PeriodController::class, 'update'])->name('update');
            Route::post('/{period}/close', [PeriodController::class, 'close'])->name('close');
            Route::delete('/{period}', [PeriodController::class, 'destroy'])->name('destroy');
        });

    // ─── DELIVERY ORDERS ─────────────────────────────────────────────────────
    Route::prefix('do')
        ->name('do.')
        ->group(function () {
            Route::get('/', [DeliveryOrderController::class, 'index'])->name('index');
            Route::get('/create', [DeliveryOrderController::class, 'create'])->name('create');
            Route::post('/', [DeliveryOrderController::class, 'store'])->name('store');
            Route::get('/{do}/edit', [DeliveryOrderController::class, 'edit'])->name('edit');
            Route::put('/{do}', [DeliveryOrderController::class, 'update'])->name('update');
            Route::delete('/{do}', [DeliveryOrderController::class, 'destroy'])->name('destroy');
        });

    // ─── DISTRIBUTIONS ────────────────────────────────────────────────────────
    Route::prefix('distributions')
        ->name('distributions.')
        ->group(function () {
            Route::get('/', [DistributionController::class, 'index'])->name('index');
            Route::get('/create', [DistributionController::class, 'create'])->name('create');
            Route::post('/', [DistributionController::class, 'store'])->name('store');
            Route::post('/bulk', [DistributionController::class, 'bulkStore'])->name('bulk-store');
            Route::get('/{distribution}/edit', [DistributionController::class, 'edit'])->name('edit');
            Route::put('/{distribution}', [DistributionController::class, 'update'])->name('update');
            Route::delete('/{distribution}', [DistributionController::class, 'destroy'])->name('destroy');
            Route::post('/{distribution}/payment', [DistributionController::class, 'recordPayment'])->name('payment');
        });

    // ─── CASHFLOW HARIAN ─────────────────────────────────────────────────────
    Route::prefix('cashflow')
        ->name('cashflow.')
        ->group(function () {
            Route::get('/', [CashflowController::class, 'index'])->name('index');
            Route::post('/', [CashflowController::class, 'store'])->name('store');
            Route::put('/{expense}', [CashflowController::class, 'update'])->name('update');
            Route::delete('/{expense}', [CashflowController::class, 'destroy'])->name('destroy');
        });

    // ─── TRANSFER ─────────────────────────────────────────────────────────────
    Route::prefix('transfer')
        ->name('transfer.')
        ->group(function () {
            Route::get('/', [TransferController::class, 'index'])->name('index');
            Route::post('/deposit', [TransferController::class, 'storeDeposit'])->name('deposit.store');
            Route::post('/account', [TransferController::class, 'storeTransfer'])->name('account.store');
            Route::delete('/deposit/{deposit}', [TransferController::class, 'destroyDeposit'])->name('deposit.destroy');
            Route::delete('/account/{transfer}', [TransferController::class, 'destroyTransfer'])->name('account.destroy');
        });

    // ─── SUMMARY ─────────────────────────────────────────────────────────────
    Route::get('/summary', [SummaryController::class, 'index'])->name('summary.index');

    // ─── EXPORT ──────────────────────────────────────────────────────────────
    Route::get('/export', [ExportController::class, 'export'])->name('export');

    // ─── TABUNGAN / SURPLUS ──────────────────────────────────────────────────
    Route::prefix('savings')
        ->name('savings.')
        ->group(function () {
            Route::get('/', [SavingController::class, 'index'])->name('index');
            Route::post('/', [SavingController::class, 'store'])->name('store');
            Route::delete('/{saving}', [SavingController::class, 'destroy'])->name('destroy');
        });

    // ─── EXTERNAL DEBT ───────────────────────────────────────────────────────
    Route::prefix('external-debt')
        ->name('external-debt.')
        ->group(function () {
            Route::get('/', [ExternalDebtController::class, 'index'])->name('index');
            Route::post('/', [ExternalDebtController::class, 'store'])->name('store');
            Route::delete('/{externalDebt}', [ExternalDebtController::class, 'destroy'])->name('destroy');
        });

    // ─── CONTRACT PAYMENTS ───────────────────────────────────────────────────
    Route::prefix('contract-payments')
        ->name('contract-payments.')
        ->group(function () {
            Route::put('/{payment}', [OutletContractPaymentController::class, 'update'])->name('update');
            Route::post('/recalc', [OutletContractPaymentController::class, 'recalc'])->name('recalc');
        });

    // ─── DISTRIBUSI KONTRAK ──────────────────────────────────────────────────
    Route::prefix('contract-distributions')
        ->name('contract-dist.')
        ->group(function () {
            Route::get('/', [ContractDistributionController::class, 'index'])->name('index');
            Route::put('/{contractDistribution}', [ContractDistributionController::class, 'update'])->name('update');
            Route::post('/{contractDistribution}/selesaikan', [ContractDistributionController::class, 'selesaikan'])->name('selesaikan');
            Route::post('/{contractDistribution}/reset', [ContractDistributionController::class, 'resetSelesaikan'])->name('reset');
            Route::post('/{contractDistribution}/cutoff', [ContractDistributionController::class, 'cutoff'])->name('cutoff');
            Route::post('/sync', [ContractDistributionController::class, 'sync'])->name('sync');
        });

    // ─── ANALISA BISNIS ───────────────────────────────────────────────────────
    Route::prefix('analysis')
        ->name('analysis.')
        ->group(function () {
            Route::get('/', [AnalysisController::class, 'index'])->name('index');
            Route::get('/analyze', [AnalysisController::class, 'analyze'])->name('analyze');
        });

    // ─── SLIP GAJI ───────────────────────────────────────────────────────────
    Route::prefix('payslip')
        ->name('payslip.')
        ->group(function () {
            Route::get('/', [PayslipController::class, 'index'])->name('index');
            Route::get('/{courier}', [PayslipController::class, 'show'])->name('show');
        });
    // ─── MASTER DATA ─────────────────────────────────────────────────────────
    Route::prefix('master')
        ->name('master.')
        ->group(function () {
            Route::get('/', [MasterController::class, 'index'])->name('index');
            Route::post('/outlets', [MasterController::class, 'storeOutlet'])->name('outlets.store');
            Route::put('/outlets/{outlet}', [MasterController::class, 'updateOutlet'])->name('outlets.update');
            Route::post('/customers', [MasterController::class, 'storeCustomer'])->name('customers.store');
            Route::put('/customers/{customer}', [MasterController::class, 'updateCustomer'])->name('customers.update');
            Route::post('/couriers', [MasterController::class, 'storeCourier'])->name('couriers.store');
            Route::put('/couriers/{courier}', [MasterController::class, 'updateCourier'])->name('couriers.update');
        });
}); // end auth middleware
