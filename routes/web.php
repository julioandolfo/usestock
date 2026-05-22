<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\User\DownloadController;
use App\Http\Controllers\User\FileServeController;
use App\Http\Controllers\User\LibraryController;
use App\Http\Controllers\User\PaymentController;
use App\Http\Controllers\Webhooks\GetStocksWebhookController;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// -------------------------------------------------------------------
// Public / pre-install
// -------------------------------------------------------------------
Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware('not_installed')->group(function () {
    Route::get('/install', [InstallController::class, 'show'])->name('install.show');
    Route::post('/install', [InstallController::class, 'store'])->name('install.store');
});

// -------------------------------------------------------------------
// Webhooks (no auth, signature verified inside)
// -------------------------------------------------------------------
Route::post('/webhooks/getstocks/{public_id}', GetStocksWebhookController::class)
    ->middleware('signed')
    ->name('webhooks.getstocks');

Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->name('webhooks.mercadopago');

// -------------------------------------------------------------------
// Authenticated user area
// -------------------------------------------------------------------
Route::middleware(['auth', 'verified', 'installed'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');

    Route::get('/downloads', [DownloadController::class, 'index'])->name('downloads.index');
    Route::post('/downloads', [DownloadController::class, 'store'])->name('downloads.store');
    Route::get('/downloads/{public_id}', [DownloadController::class, 'show'])->name('downloads.show');

    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{public_id}/file', FileServeController::class)
        ->middleware('signed')
        ->name('library.file');

    Route::get('/billing', [PaymentController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout', [PaymentController::class, 'checkout'])->name('billing.checkout');
});

// -------------------------------------------------------------------
// Admin area
// -------------------------------------------------------------------
Route::middleware(['auth', 'verified', 'installed', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => Inertia::render('admin/dashboard'))->name('dashboard');
        // TODO: scaffold the rest of admin pages (users, providers, packages, settings, audit).
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
