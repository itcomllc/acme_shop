<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\{SSLController, SSLDashboardController, SquareWebhookController};

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// SSL Dashboard Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ssl/dashboard', function () {
        return view('ssl.dashboard');
    })->name('ssl.dashboard');
    
    Route::get('/ssl/certificate/{certificate}/download', [SSLController::class, 'downloadCertificate'])
        ->name('ssl.certificate.download');
    
    // Billing routes
    Route::get('/billing', function () {
        return view('billing.index');
    })->name('billing.index');
    
    Route::get('/ssl/billing', function () {
        return redirect()->route('billing.index');
    });
});

// API Routes for SSL SaaS
Route::group(['prefix' => 'api/ssl', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/dashboard', [SSLDashboardController::class, 'dashboard']);
    Route::post('/subscription', [SSLDashboardController::class, 'createSubscription']);
    Route::post('/certificate', [SSLDashboardController::class, 'issueCertificate']);
    Route::get('/certificate/{certificate}/validation', [SSLDashboardController::class, 'getCertificateValidation']);
    Route::get('/certificate/{certificate}/download', [SSLDashboardController::class, 'downloadCertificate']);
});

// Webhook endpoint (no auth middleware)
Route::post('/webhooks/square', [SquareWebhookController::class, 'handleWebhook'])
    ->name('webhooks.square');

// Settings Routes
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';