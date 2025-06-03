<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Acme\{
    AcmeDirectoryController,
    AcmeAccountController,
    AcmeOrderController,
    AcmeAuthorizationController,
    AcmeChallengeController,
    AcmeCertificateController
};

// ACME v2 API Routes (RFC 8555)
Route::prefix('acme')->name('acme.')->group(function () {
    
    // Directory (GET) - RFC 8555 Section 7.1.1
    Route::get('directory', [AcmeDirectoryController::class, 'directory'])->name('directory');
    
    // New Nonce (HEAD/GET) - RFC 8555 Section 7.2
    Route::match(['HEAD', 'GET'], 'new-nonce', [AcmeDirectoryController::class, 'newNonce'])->name('new-nonce');
    
    // Account Management - RFC 8555 Section 7.3
    Route::post('new-account', [AcmeAccountController::class, 'newAccount'])->name('new-account');
    Route::post('account/{account}', [AcmeAccountController::class, 'updateAccount'])->name('account');
    Route::get('account/{account}/orders', [AcmeAccountController::class, 'orders'])->name('account.orders');
    Route::post('key-change', [AcmeAccountController::class, 'keyChange'])->name('key-change');
    
    // Order Management - RFC 8555 Section 7.4
    Route::post('new-order', [AcmeOrderController::class, 'newOrder'])->name('new-order');
    Route::post('order/{order}', [AcmeOrderController::class, 'getOrder'])->name('order');
    Route::post('order/{order}/finalize', [AcmeOrderController::class, 'finalizeOrder'])->name('order.finalize');
    
    // Authorization Management - RFC 8555 Section 7.5
    Route::post('authz/{authorization}', [AcmeAuthorizationController::class, 'getAuthorization'])->name('authorization');
    
    // Challenge Management - RFC 8555 Section 7.5.1
    Route::post('chall/{challenge}', [AcmeChallengeController::class, 'challenge'])->name('challenge');
    
    // Certificate Management - RFC 8555 Section 7.4.2
    Route::post('cert/{certificate}', [AcmeCertificateController::class, 'getCertificate'])->name('certificate');
    
    // Certificate Revocation - RFC 8555 Section 7.6
    Route::post('revoke-cert', [AcmeCertificateController::class, 'revokeCertificate'])->name('revoke-cert');
    
    // Terms of Service
    Route::get('terms', function () {
        return view('acme.terms');
    })->name('terms');
    
    // Challenge validation endpoints (for HTTP-01)
    Route::get('.well-known/acme-challenge/{token}', [AcmeChallengeController::class, 'httpChallenge'])
        ->name('http-challenge');
});

// EAB Management (Dashboard用)
Route::middleware(['auth', 'ssl.subscription.active'])->prefix('ssl/eab')->name('ssl.eab.')->group(function () {
    Route::get('/', function () {
        return view('ssl.eab.index');
    })->name('index');
    
    Route::post('generate', [\App\Http\Controllers\EabController::class, 'generate'])->name('generate');
    Route::post('{credential}/revoke', [\App\Http\Controllers\EabController::class, 'revoke'])->name('revoke');
    Route::get('instructions', function () {
        return view('ssl.eab.instructions');
    })->name('instructions');
});
