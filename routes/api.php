<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    SSLCertificateController,
    SSLSubscriptionController,
    SSLProviderController,
    SSLHealthController
};

// SSL API Routes (認証必須)
Route::middleware(['auth:sanctum'])->prefix('ssl')->name('ssl.api.')->group(function () {
    
    // Certificate Management
    Route::apiResource('certificates', SSLCertificateController::class);
    Route::prefix('certificates/{certificate}')->name('certificates.')->group(function () {
        Route::post('renew', [SSLCertificateController::class, 'renew'])->name('renew');
        Route::post('revoke', [SSLCertificateController::class, 'revoke'])->name('revoke');
        Route::get('download', [SSLCertificateController::class, 'download'])->name('download');
        Route::get('validation-status', [SSLCertificateController::class, 'validationStatus'])->name('validation-status');
    });

    // Subscription Management
    Route::apiResource('subscriptions', SSLSubscriptionController::class);
    Route::prefix('subscriptions/{subscription}')->name('subscriptions.')->group(function () {
        Route::post('add-domain', [SSLSubscriptionController::class, 'addDomain'])->name('add-domain');
        Route::delete('remove-domain/{domain}', [SSLSubscriptionController::class, 'removeDomain'])->name('remove-domain');
        Route::post('change-provider', [SSLSubscriptionController::class, 'changeProvider'])->name('change-provider');
        Route::get('statistics', [SSLSubscriptionController::class, 'statistics'])->name('statistics');
        Route::post('pause', [SSLSubscriptionController::class, 'pause'])->name('pause');
        Route::post('resume', [SSLSubscriptionController::class, 'resume'])->name('resume');
    });

    // Provider Management
    Route::prefix('providers')->name('providers.')->group(function () {
        Route::get('/', [SSLProviderController::class, 'index'])->name('index');
        Route::get('compare', [SSLProviderController::class, 'compare'])->name('compare');
        Route::get('recommendations', [SSLProviderController::class, 'recommendations'])->name('recommendations');
        Route::post('test-connection/{provider}', [SSLProviderController::class, 'testConnection'])->name('test-connection');
        Route::get('health-check', [SSLProviderController::class, 'healthCheck'])->name('health-check');
    });

    // Health & Monitoring
    Route::prefix('health')->name('health.')->group(function () {
        Route::get('/', [SSLHealthController::class, 'index'])->name('index');
        Route::get('certificates', [SSLHealthController::class, 'certificates'])->name('certificates');
        Route::get('providers', [SSLHealthController::class, 'providers'])->name('providers');
        Route::get('subscriptions', [SSLHealthController::class, 'subscriptions'])->name('subscriptions');
    });

    // Bulk Operations
    Route::prefix('bulk')->name('bulk.')->group(function () {
        Route::post('certificates/renew', [SSLCertificateController::class, 'bulkRenew'])->name('certificates.renew');
        Route::post('certificates/revoke', [SSLCertificateController::class, 'bulkRevoke'])->name('certificates.revoke');
        Route::post('subscriptions/migrate-provider', [SSLSubscriptionController::class, 'bulkMigrateProvider'])->name('subscriptions.migrate-provider');
    });
});

// Public endpoints (認証不要)
Route::prefix('ssl/public')->name('ssl.public.')->group(function () {
    Route::get('health', [SSLHealthController::class, 'publicHealth'])->name('health');
    Route::get('providers/status', [SSLProviderController::class, 'publicStatus'])->name('providers.status');
});

// Webhook endpoints (認証不要)
Route::prefix('ssl/webhooks')->name('ssl.webhooks.')->group(function () {
    Route::post('gogetssl', [SSLCertificateController::class, 'gogetSSLWebhook'])->name('gogetssl');
    Route::post('google-cm', [SSLCertificateController::class, 'googleCMWebhook'])->name('google-cm');
    Route::post('lets-encrypt', [SSLCertificateController::class, 'letsEncryptWebhook'])->name('lets-encrypt');
});