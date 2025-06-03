<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    SSLController,
    SSLDashboardController,
    SquareWebhookController
};

// SSL Web Routes - 認証必須
Route::middleware(['auth', 'verified'])->prefix('ssl')->name('ssl.')->group(function () {
    
    // Dashboard
    Route::get('/', function () {
        return view('ssl.dashboard');
    })->name('dashboard');
    
    Route::get('/overview', [SSLDashboardController::class, 'overview'])->name('overview');
    
    // Certificate Management (Web UI)
    Route::get('/certificates', function () {
        return view('ssl.certificates.index');
    })->name('certificates.index');
    
    Route::get('/certificates/{certificate}', function () {
        return view('ssl.certificates.show');
    })->name('certificates.show');
    
    Route::get('/certificate/{certificate}/download', [SSLController::class, 'downloadCertificate'])
        ->name('certificate.download');
    
    // Subscription Management (Web UI)
    Route::get('/subscriptions', function () {
        return view('ssl.subscriptions.index');
    })->name('subscriptions.index');
    
    Route::get('/subscriptions/{subscription}', function () {
        return view('ssl.subscriptions.show');
    })->name('subscriptions.show');
    
    // Billing routes
    Route::get('/billing', function () {
        return view('billing.index');
    })->name('billing.index');
    
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
    Route::get('providers/manage', function () {
        return view('admin.ssl.providers.manage');
    })->name('providers.manage');
    
    // User Management
    Route::get('users', [SSLDashboardController::class, 'userManagement'])->name('users');
    Route::get('subscriptions/all', function () {
        return view('admin.ssl.subscriptions.index');
    })->name('subscriptions.all');
    
    // System Health
    Route::get('health/detailed', function () {
        return view('admin.ssl.health.detailed');
    })->name('health.detailed');
});

// Webhook endpoint (no auth middleware)
Route::post('/webhooks/square', [SquareWebhookController::class, 'handleWebhook'])
    ->name('webhooks.square');

// API Routes for SSL SaaS (Dashboard用の内部API)
Route::group(['prefix' => 'api/ssl', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/dashboard', [SSLDashboardController::class, 'dashboard']);
    Route::post('/subscription', [SSLDashboardController::class, 'createSubscription']);
    Route::post('/certificate', [SSLDashboardController::class, 'issueCertificate']);
    Route::get('/certificate/{certificate}/validation', [SSLDashboardController::class, 'getCertificateValidation']);
    Route::get('/certificate/{certificate}/download', [SSLDashboardController::class, 'downloadCertificate']);
});

// Documentation routes
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
        Route::get('test-providers', function () {
            return view('ssl.dev.test-providers');
        })->name('test-providers');
        
        Route::get('simulate-failure/{certificate}', function () {
            return view('ssl.dev.simulate-failure');
        })->name('simulate-failure');
    });
}