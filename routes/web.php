<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ProductionLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockUsageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('role:owner,inventory_staff')->group(function () {
        Route::get('/master/{resource}', [DataController::class, 'index'])->whereIn('resource', ['ingredient-categories', 'units', 'suppliers', 'ingredients'])->name('data.index');
        Route::post('/master/{resource}', [DataController::class, 'store'])->whereIn('resource', ['ingredient-categories', 'units', 'suppliers', 'ingredients'])->name('data.store');
        Route::put('/master/{resource}/{id}', [DataController::class, 'update'])->whereNumber('id')->name('data.update');
        Route::delete('/master/{resource}/{id}', [DataController::class, 'destroy'])->whereNumber('id')->name('data.destroy');

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');

        Route::get('/stock-usages', [StockUsageController::class, 'index'])->name('stock-usages.index');
        Route::post('/stock-usages', [StockUsageController::class, 'store'])->name('stock-usages.store');
        Route::put('/stock-usages/{stockUsage}', [StockUsageController::class, 'update'])->name('stock-usages.update');
        Route::delete('/stock-usages/{stockUsage}', [StockUsageController::class, 'destroy'])->name('stock-usages.destroy');

        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
        Route::put('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'update'])->name('stock-adjustments.update');
    });

    Route::middleware('role:owner')->group(function () {
        Route::post('/stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
        Route::post('/stock-adjustments/{stockAdjustment}/cancel', [StockAdjustmentController::class, 'cancel'])->name('stock-adjustments.cancel');
        Route::post('/production-logs/{productionLog}/cancel', [ProductionLogController::class, 'cancel'])->name('production-logs.cancel');
        Route::post('/stock-usages/{stockUsage}/cancel', [StockUsageController::class, 'cancel'])->name('stock-usages.cancel');
        Route::get('/admin/{resource}', [DataController::class, 'index'])->whereIn('resource', ['users', 'settings', 'activity-logs'])->name('admin.index');
        Route::post('/admin/{resource}', [DataController::class, 'store'])->whereIn('resource', ['users', 'settings'])->name('admin.store');
        Route::put('/admin/{resource}/{id}', [DataController::class, 'update'])->whereNumber('id')->name('admin.update');
        Route::delete('/admin/{resource}/{id}', [DataController::class, 'destroy'])->whereNumber('id')->name('admin.destroy');
        Route::get('/reports/{report}/export/{format}', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::get('/production-logs', [ProductionLogController::class, 'index'])
        ->middleware('role:owner,inventory_staff,barista')
        ->name('production-logs.index');

    Route::middleware('role:owner,barista')->group(function () {
        Route::post('/production-logs', [ProductionLogController::class, 'store'])->name('production-logs.store');
    });

    Route::middleware('role:owner,inventory_staff,barista')->group(function () {
        Route::get('/menu/{resource}', [DataController::class, 'index'])->whereIn('resource', ['menu-items', 'recipe-items'])->name('menu.index');
        Route::post('/menu/{resource}', [DataController::class, 'store'])->middleware('role:owner')->whereIn('resource', ['menu-items', 'recipe-items'])->name('menu.store');
        Route::put('/menu/{resource}/{id}', [DataController::class, 'update'])->middleware('role:owner')->whereNumber('id')->name('menu.update');
        Route::delete('/menu/{resource}/{id}', [DataController::class, 'destroy'])->middleware('role:owner')->whereNumber('id')->name('menu.destroy');

        Route::get('/monitoring/{resource}', [DataController::class, 'index'])->whereIn('resource', ['ingredients', 'low-stock', 'out-of-stock', 'stock-movements'])->name('monitoring.index');
        Route::get('/reports/{report}', [ReportController::class, 'index'])
            ->middleware('role:owner,inventory_staff')
            ->name('reports.index');
    });
});

require __DIR__.'/auth.php';
