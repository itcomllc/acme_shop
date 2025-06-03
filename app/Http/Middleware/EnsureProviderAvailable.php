<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Log};
use App\Services\CertificateProviderFactory;

class EnsureProviderAvailable
{
    public function __construct(
        private readonly CertificateProviderFactory $providerFactory
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $provider = null)
    {
        // プロバイダーが指定されていない場合は、リクエストから取得
        if (!$provider) {
            $provider = $request->route('provider') ?? $request->input('provider');
        }

        if (!$provider) {
            return $next($request);
        }

        // キャッシュされたプロバイダー状態をチェック
        $cacheKey = "ssl_provider_status_{$provider}";
        $isAvailable = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($provider) {
            try {
                $errors = $this->providerFactory->validateProviderConfig($provider);
                return empty($errors);
            } catch (\Exception $e) {
                Log::warning('Provider availability check failed', [
                    'provider' => $provider,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });

        if (!$isAvailable) {
            Log::warning('Request blocked due to unavailable provider', [
                'provider' => $provider,
                'route' => $request->route()?->getName(),
                'user_id' => $request->user()?->id
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "SSL provider '{$provider}' is currently unavailable",
                    'provider' => $provider
                ], 503);
            }

            return redirect()->back()
                ->with('error', "SSL provider '{$provider}' is currently unavailable. Please try again later.");
        }

        return $next($request);
    }
}