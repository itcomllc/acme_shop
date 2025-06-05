<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    SSLController,
    SSLDashboardController,
    SquareWebhookController,
    EabController  // 追加
};
use App\Http\Controllers\Admin\{
    AdminDashboardController,    // 追加
    AdminRoleController,
    AdminUserController
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
    
    // EAB Management (Web UI) - アクティブサブスクリプション必須
    Route::middleware('ssl.subscription.active')->prefix('eab')->name('eab.')->group(function () {
        Route::get('/', [EabController::class, 'index'])->name('index');
        Route::post('generate', [EabController::class, 'generate'])->name('generate');
        Route::post('{credential}/revoke', [EabController::class, 'revoke'])->name('revoke');
        Route::get('instructions', [EabController::class, 'instructions'])->name('instructions');
        Route::get('credentials/{credential}', [EabController::class, 'show'])->name('show');
    });
    
    // Reports & Analytics
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('certificates', [SSLDashboardController::class, 'certificateReport'])->name('certificates');
        Route::get('usage', [SSLDashboardController::class, 'usageReport'])->name('usage');
        Route::get('provider-performance', [SSLDashboardController::class, 'providerPerformance'])->name('provider-performance');
        Route::get('export/{type}', [SSLDashboardController::class, 'export'])->name('export');
    });
});

// Admin routes (requires admin permissions)
Route::middleware(['auth', 'permission:admin.access'])->prefix('admin')->name('admin.')->group(function () {
    
    // Admin Dashboard - AdminDashboardController を追加
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/system-overview', [AdminDashboardController::class, 'systemOverview'])->name('system.overview');
    
    // 既存のUser Management routes
    Route::middleware('permission:users.view_all')->group(function () {
        Route::resource('users', AdminUserController::class);
        Route::post('users/{user}/assign-role', [AdminUserController::class, 'assignRole'])->name('users.assign-role');
        Route::delete('users/{user}/roles/{role}', [AdminUserController::class, 'removeRole'])->name('users.remove-role');
        Route::post('users/{user}/resend-verification', [AdminUserController::class, 'resendVerification'])->name('users.resend-verification');
        Route::post('users/bulk-update', [AdminUserController::class, 'bulkUpdate'])->name('users.bulk-update');
        Route::get('users-statistics', [AdminUserController::class, 'statistics'])->name('users.statistics');
    });
    
    // 既存のRole Management routes
    Route::middleware('permission:admin.roles.manage')->group(function () {
        Route::resource('roles', AdminRoleController::class);
        Route::post('roles/{role}/assign-user', [AdminRoleController::class, 'assignToUser'])->name('roles.assign-user');
        Route::delete('roles/{role}/users', [AdminRoleController::class, 'removeFromUser'])->name('roles.remove-user');
        Route::post('roles/bulk-assign', [AdminRoleController::class, 'bulkAssign'])->name('roles.bulk-assign');
        Route::post('roles/bulk-action', [AdminRoleController::class, 'bulkActions'])->name('roles.bulk-action');
        Route::get('roles-statistics', [AdminRoleController::class, 'statistics'])->name('roles.statistics');
    });
    
    // SSL System Administration
    Route::middleware('permission:ssl.certificates.view_all')->prefix('ssl')->name('ssl.')->group(function () {
        Route::get('system', [SSLDashboardController::class, 'systemOverview'])->name('system');
        Route::get('providers/manage', function () {
            return view('admin.ssl.providers.manage');
        })->name('providers.manage');
        
        Route::get('subscriptions/all', function () {
            return view('admin.ssl.subscriptions.index');
        })->name('subscriptions.all');
        
        Route::get('health/detailed', function () {
            return view('admin.ssl.health.detailed');
        })->name('health.detailed');
    });
    
    // System Management
    Route::middleware('permission:system.health.view')->group(function () {
        Route::get('system/health', function () {
            return view('admin.system.health');
        })->name('system.health');
        
        Route::get('system/logs', function () {
            return view('admin.system.logs');
        })->name('system.logs')->middleware('permission:system.logs.view');
    });
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


Route::middleware('auth')->get('/debug-permissions', function () {
    $user = Auth::user();
    
    if (!$user) {
        return 'Not authenticated';
    }
    
    $data = [
        'user' => $user->only(['id', 'name', 'email']),
        'primary_role' => $user->primaryRole ? [
            'id' => $user->primaryRole->id,
            'name' => $user->primaryRole->name,
            'display_name' => $user->primaryRole->display_name
        ] : null,
        'all_roles' => $user->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name
            ];
        }),
        'has_admin_access' => $user->hasPermission('admin.access'),
        'can_access_admin' => method_exists($user, 'canAccessAdmin') ? $user->canAccessAdmin() : 'method not found',
        'all_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        'roles_with_admin_access' => \App\Models\Role::whereHas('permissions', function ($q) {
            $q->where('name', 'admin.access');
        })->get(['name', 'display_name'])
    ];
    
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});
