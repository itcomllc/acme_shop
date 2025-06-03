<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\SSLController;
use App\Http\Controllers\SSLDashboardController;
use App\Http\Controllers\SquareWebhookController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::group(['prefix' => 'api/ssl', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/dashboard', [SSLDashboardController::class, 'dashboard']);
    Route::post('/subscription', [SSLDashboardController::class, 'createSubscription']);
    Route::post('/certificate', [SSLDashboardController::class, 'issueCertificate']);
    Route::get('/certificate/{certificate}/validation', [SSLDashboardController::class, 'getCertificateValidation']);
    Route::get('/certificate/{certificate}/download', [SSLDashboardController::class, 'downloadCertificate']);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/ssl/dashboard', function () {
        return view('ssl.dashboard');
    })->name('ssl.dashboard');
    
    Route::get('/ssl/certificate/{certificate}/download', [SSLController::class, 'downloadCertificate'])
        ->name('ssl.certificate.download');
});

// Webhook endpoint (no auth middleware)
Route::post('/webhooks/square', [SquareWebhookController::class, 'handleWebhook']);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
