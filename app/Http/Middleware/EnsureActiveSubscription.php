<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        $subscription = $user->activeSubscription;
        
        if (!$subscription || !$subscription->isActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active subscription required',
                    'redirect' => route('ssl.subscriptions.index')
                ], 403);
            }

            return redirect()->route('ssl.subscriptions.index')
                ->with('error', 'You need an active subscription to access this feature.');
        }

        return $next($request);
    }
}