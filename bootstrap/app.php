<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function () {
            // SSL専用ルーティング
            Route::middleware('web')
                ->group(base_path('routes/ssl.php'));
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/acme.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // エイリアスミドルウェアを登録
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'ssl.subscription.active' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'ssl.provider.available' => \App\Http\Middleware\EnsureProviderAvailable::class,
        ]);
        // グローバルミドルウェア
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // 必要に応じてグローバルミドルウェアを追加
            // Livewire用のミドルウェア（必要に応じて）
            // \Livewire\Middleware\HydrationMiddleware::class,
        ]);
        // APIミドルウェア
        $middleware->api(append: [
            // API用のミドルウェアを追加
        ]);

        // SSL API用のレート制限
        $middleware->throttleApi('ssl-api:120,1');

        // Webhook用のレート制限
        $middleware->throttleApi('webhooks:100,1');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // SSL関連の例外処理
        $exceptions->renderable(function (\App\Exceptions\SSLProviderUnavailableException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'SSL provider is currently unavailable',
                    'provider' => $e->getProvider(),
                    'error' => $e->getMessage()
                ], 503);
            }

            return redirect()->route('ssl.dashboard')
                ->with('error', 'SSL provider is currently unavailable. Please try again later.');
        });

        $exceptions->renderable(function (\App\Exceptions\CertificateLimitExceededException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate limit exceeded for your subscription',
                    'current_count' => $e->getCurrentCount(),
                    'limit' => $e->getLimit()
                ], 422);
            }

            return redirect()->route('ssl.dashboard')
                ->with('error', 'You have reached the certificate limit for your subscription plan.');
        });
    })->create();
