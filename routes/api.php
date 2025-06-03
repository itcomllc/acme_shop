<?php
// routes/api.php - SSL API Routes

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    SSLCertificateController,
    SSLSubscriptionController,
    SSLProviderController,
    SSLHealthController
};

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

// Public endpoints (no authentication required)
Route::prefix('ssl/public')->name('ssl.public.')->group(function () {
    Route::get('health', [SSLHealthController::class, 'publicHealth'])->name('health');
    Route::get('providers/status', [SSLProviderController::class, 'publicStatus'])->name('providers.status');
});

// Webhook endpoints
Route::prefix('ssl/webhooks')->name('ssl.webhooks.')->group(function () {
    Route::post('gogetssl', [SSLCertificateController::class, 'gogetSSLWebhook'])->name('gogetssl');
    Route::post('google-cm', [SSLCertificateController::class, 'googleCMWebhook'])->name('google-cm');
    Route::post('lets-encrypt', [SSLCertificateController::class, 'letsEncryptWebhook'])->name('lets-encrypt');
});

// ===============================================
// routes/web.php - SSL Web Routes
// ===============================================

use App\Http\Controllers\Web\{
    SSLDashboardController,
    SSLCertificateWebController,
    SSLSubscriptionWebController
};

Route::middleware(['auth'])->prefix('ssl')->name('ssl.')->group(function () {
    
    // Dashboard
    Route::get('/', [SSLDashboardController::class, 'index'])->name('dashboard');
    Route::get('/overview', [SSLDashboardController::class, 'overview'])->name('overview');

    // Certificate Management (Web UI)
    Route::resource('certificates', SSLCertificateWebController::class);
    Route::prefix('certificates/{certificate}')->name('certificates.')->group(function () {
        Route::get('details', [SSLCertificateWebController::class, 'details'])->name('details');
        Route::post('renew-form', [SSLCertificateWebController::class, 'renewForm'])->name('renew-form');
        Route::post('revoke-form', [SSLCertificateWebController::class, 'revokeForm'])->name('revoke-form');
    });

    // Subscription Management (Web UI)
    Route::resource('subscriptions', SSLSubscriptionWebController::class);
    Route::prefix('subscriptions/{subscription}')->name('subscriptions.')->group(function () {
        Route::get('manage', [SSLSubscriptionWebController::class, 'manage'])->name('manage');
        Route::get('billing', [SSLSubscriptionWebController::class, 'billing'])->name('billing');
        Route::get('settings', [SSLSubscriptionWebController::class, 'settings'])->name('settings');
    });

    // Reports & Analytics
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('certificates', [SSLDashboardController::class, 'certificateReport'])->name('certificates');
        Route::get('usage', [SSLDashboardController::class, 'usageReport'])->name('usage');
        Route::get('provider-performance', [SSLDashboardController::class, 'providerPerformance'])->name('provider-performance');
        Route::get('export/{type}', [SSLDashboardController::class, 'export'])->name('export');
    });
});

// Admin routes (requires admin role)
Route::middleware(['auth', 'role:admin'])->prefix('admin/ssl')->name('admin.ssl.')->group(function () {
    
    // System Administration
    Route::get('system', [SSLDashboardController::class, 'systemOverview'])->name('system');
    Route::get('providers/manage', [SSLProviderController::class, 'manage'])->name('providers.manage');
    Route::post('providers/{provider}/configure', [SSLProviderController::class, 'configure'])->name('providers.configure');
    
    // User Management
    Route::get('users', [SSLDashboardController::class, 'userManagement'])->name('users');
    Route::get('subscriptions/all', [SSLSubscriptionWebController::class, 'adminIndex'])->name('subscriptions.all');
    
    // System Health
    Route::get('health/detailed', [SSLHealthController::class, 'detailedHealth'])->name('health.detailed');
    Route::post('health/run-diagnostics', [SSLHealthController::class, 'runDiagnostics'])->name('health.diagnostics');
    
    // Maintenance
    Route::post('maintenance/clear-cache', [SSLDashboardController::class, 'clearCache'])->name('maintenance.clear-cache');
    Route::post('maintenance/force-renewal/{certificate}', [SSLCertificateWebController::class, 'forceRenewal'])->name('maintenance.force-renewal');
    Route::post('maintenance/reset-provider/{provider}', [SSLProviderController::class, 'resetProvider'])->name('maintenance.reset-provider');
});

// API Documentation routes
Route::prefix('ssl/docs')->name('ssl.docs.')->group(function () {
    Route::get('/', function () {
        return view('ssl.docs.index');
    })->name('index');
    
    Route::get('api', function () {
        return view('ssl.docs.api');
    })->name('api');
    
    Route::get('webhooks', function () {
        return view('ssl.docs.webhooks');
    })->name('webhooks');
});

// Development routes (only in non-production)
if (!app()->environment('production')) {
    Route::middleware(['auth'])->prefix('ssl/dev')->name('ssl.dev.')->group(function () {
        Route::get('test-providers', [SSLProviderController::class, 'testAll'])->name('test-providers');
        Route::get('simulate-failure/{certificate}', [SSLCertificateWebController::class, 'simulateFailure'])->name('simulate-failure');
        Route::get('mock-webhook/{provider}', [SSLCertificateController::class, 'mockWebhook'])->name('mock-webhook');
        Route::get('debug-subscription/{subscription}', [SSLSubscriptionWebController::class, 'debug'])->name('debug-subscription');
    });
}
