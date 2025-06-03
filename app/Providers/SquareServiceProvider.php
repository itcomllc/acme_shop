<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\{SquareConfigService, SSLSaaSService, GoGetSSLService, AcmeService};
use Square\SquareClient;

class SquareServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Square Clientをシングルトンとして登録
        $this->app->singleton(SquareClient::class, function ($app) {
            return SquareConfigService::createClient();
        });

        // SSL SaaSサービスを登録
        $this->app->singleton(SSLSaaSService::class, function ($app) {
            return new SSLSaaSService(
                $app->make(SquareClient::class),
                $app->make(GoGetSSLService::class),
                $app->make(AcmeService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 起動時に設定の妥当性をチェック（本番環境のみ）
        if ($this->app->environment('production')) {
            $errors = SquareConfigService::validateConfig();
            if (!empty($errors)) {
                throw new \RuntimeException('Square configuration errors: ' . implode(', ', $errors));
            }
        }
    }
}
