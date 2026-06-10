<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CreditPackageController;
use App\Http\Controllers\Admin\PricingRuleController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\User\BatchController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\DownloadController;
use App\Http\Controllers\User\FileServeController;
use App\Http\Controllers\User\LibraryController;
use App\Http\Controllers\User\PaymentController;
use App\Http\Controllers\Webhooks\GetStocksWebhookController;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------
// Public / pre-install
// -------------------------------------------------------------------
Route::get('/', HomeController::class)->name('home');

Route::middleware('not_installed')->group(function () {
    Route::get('/install', [InstallController::class, 'show'])->name('install.show');
    Route::post('/install', [InstallController::class, 'store'])->name('install.store');
});

// -------------------------------------------------------------------
// Webhooks (no CSRF — see bootstrap/app.php)
// -------------------------------------------------------------------
Route::post('/webhooks/getstocks/{public_id}', GetStocksWebhookController::class)
    ->middleware('signed')
    ->name('webhooks.getstocks');

Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->name('webhooks.mercadopago');

// -------------------------------------------------------------------
// Authenticated user area
// -------------------------------------------------------------------
Route::middleware(['auth', 'installed'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/downloads', [DownloadController::class, 'index'])->name('downloads.index');
    Route::post('/downloads', [DownloadController::class, 'store'])
        ->middleware('throttle_downloads')
        ->name('downloads.store');
    Route::post('/downloads/preview', [DownloadController::class, 'preview'])
        ->middleware('throttle:60,1')
        ->name('downloads.preview');
    Route::get('/downloads/{public_id}', [DownloadController::class, 'show'])->name('downloads.show');

    Route::get('/batches/{public_id}', [BatchController::class, 'show'])->name('batches.show');
    Route::get('/batches/{public_id}/zip', [BatchController::class, 'zip'])->name('batches.zip');

    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{public_id}/file', FileServeController::class)->name('library.file');

    Route::get('/billing', [PaymentController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout', [PaymentController::class, 'checkout'])->name('billing.checkout');
});

// -------------------------------------------------------------------
// Admin area
// -------------------------------------------------------------------
Route::middleware(['auth', 'installed', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/credits', [AdminUserController::class, 'adjustCredits'])->name('users.credits');
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban');
        Route::post('/users/{user}/unban', [AdminUserController::class, 'unban'])->name('users.unban');
        Route::post('/users/{user}/toggle-admin', [AdminUserController::class, 'toggleAdmin'])->name('users.toggle-admin');

        Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
        Route::patch('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
        Route::post('/providers/{provider}/price', [ProviderController::class, 'setPrice'])->name('providers.price');
        Route::post('/providers/bulk/{slug}', [ProviderController::class, 'bulkUpdate'])->name('providers.bulk');
        Route::post('/providers/sync', [ProviderController::class, 'sync'])->name('providers.sync');

        Route::get('/pricing', [PricingRuleController::class, 'index'])->name('pricing.index');
        Route::post('/pricing', [PricingRuleController::class, 'store'])->name('pricing.store');
        Route::patch('/pricing/{rule}', [PricingRuleController::class, 'update'])->name('pricing.update');
        Route::delete('/pricing/{rule}', [PricingRuleController::class, 'destroy'])->name('pricing.destroy');

        Route::get('/packages', [CreditPackageController::class, 'index'])->name('packages.index');
        Route::post('/packages', [CreditPackageController::class, 'store'])->name('packages.store');
        Route::patch('/packages/{package}', [CreditPackageController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{package}', [CreditPackageController::class, 'destroy'])->name('packages.destroy');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::post('/settings/getstocks', [SettingsController::class, 'updateGetstocks'])->name('settings.getstocks');
        Route::post('/settings/mercadopago', [SettingsController::class, 'updateMercadoPago'])->name('settings.mercadopago');
        Route::post('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail');
        Route::post('/settings/downloads', [SettingsController::class, 'updateDownloads'])->name('settings.downloads');

        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
