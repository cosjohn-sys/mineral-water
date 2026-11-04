<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GallonExchangeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReversalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('customers', CustomerController::class)->where(['customer' => '[0-9]+']);
    Route::resource('products', ProductController::class)->where(['product' => '[0-9]+']);
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show'])->where(['sale' => '[0-9]+']);
    Route::post('sales/{sale}/payments', [PaymentController::class, 'store'])->whereNumber('sale')->name('sales.payments.store');
    Route::get('gallon-exchanges', [GallonExchangeController::class, 'index'])->name('gallon-exchanges.index');
    Route::put('sales/{sale}/gallon-exchange', [GallonExchangeController::class, 'update'])->whereNumber('sale')->name('sales.gallon-exchange.update');
    Route::resource('expenses', ExpenseController::class)->where(['expense' => '[0-9]+']);
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('payments/{payment}/reversal', [PaymentReversalController::class, 'store'])->whereNumber('payment')->name('payments.reversal.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
