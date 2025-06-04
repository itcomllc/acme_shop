<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Base Admin Controller
 * 基本的なAdmin機能を提供する基底クラス
 */
abstract class AdminControllerBase extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:admin.access',
        ];
    }

    /**
     * Create middleware for specific methods
     */
    protected static function middlewareFor(string $middleware, array $only = [], array $except = []): Middleware
    {
        $middlewareInstance = new Middleware($middleware);
        
        if (!empty($only)) {
            $middlewareInstance->only($only);
        }
        
        if (!empty($except)) {
            $middlewareInstance->except($except);
        }
        
        return $middlewareInstance;
    }

    /**
     * Check if current user can perform action
     */
    protected function authorize(string $permission): void
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        if (!$user->hasPermission($permission)) {
            abort(403, 'Insufficient permissions');
        }
    }

    /**
     * Check if current user has any of the given permissions
     */
    protected function authorizeAny(array $permissions): void
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }
        
        abort(403, 'Insufficient permissions');
    }

    /**
     * Check if current user has all of the given permissions
     */
    protected function authorizeAll(array $permissions): void
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        
        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                abort(403, 'Insufficient permissions');
            }
        }
    }

    /**
     * Check if current user has specific role
     */
    protected function authorizeRole(string $role): void
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        if (!$user->hasRole($role)) {
            abort(403, 'Insufficient role permissions');
        }
    }

    /**
     * Check if current user has any of the given roles
     */
    protected function authorizeAnyRole(array $roles): void
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        if (!$user->hasAnyRole($roles)) {
            abort(403, 'Insufficient role permissions');
        }
    }

    /**
     * Get authenticated admin user
     */
    protected function getAuthenticatedAdmin()
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        
        if (!$user->canAccessAdmin()) {
            abort(403, 'Admin access required');
        }
        
        return $user;
    }

    /**
     * Log admin action
     */
    protected function logAdminAction(string $action, array $data = []): void
    {
        Log::info("Admin action: {$action}", array_merge([
            'admin_id' => Auth::id(),
            'admin_email' => Auth::user()->email,
            'timestamp' => now()->toISOString(),
        ], $data));
    }
}