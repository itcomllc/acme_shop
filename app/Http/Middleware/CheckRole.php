<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log};

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            return $this->handleUnauthorized($request, 'Authentication required');
        }

        // Check if user has any of the required roles
        if (!$user->hasAnyRole($roles)) {
            Log::warning('Access denied - insufficient role', [
                'user_id' => $user->id,
                'required_roles' => $roles,
                'user_roles' => $user->roles->pluck('name')->toArray(),
                'route' => $request->route()?->getName(),
                'ip' => $request->ip()
            ]);

            return $this->handleUnauthorized($request, 'Insufficient permissions');
        }

        return $next($request);
    }

    /**
     * Handle unauthorized access
     */
    private function handleUnauthorized(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'redirect' => route('dashboard')
            ], 403);
        }

        return redirect()->route('dashboard')
            ->with('error', $message);
    }
}