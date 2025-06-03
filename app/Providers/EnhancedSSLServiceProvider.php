<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\{
    SquareConfigService, 
    EnhancedSSLSaaSService, 
    GoGetSSLService, 
    GoogleCertificateManagerService,
    CertificateProviderFactory,
    AcmeService
};
use Square\SquareClient;

class EnhancedSSLServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Square Client（既存）
        $this->app->singleton(SquareClient::class, function ($app) {
            return SquareConfigService::createClient();
        });

        // GoGetSSL Service（既存）
        $this->app->singleton(GoGetSSLService::class, function ($app) {
            return new GoGetSSLService();
        });

        // Google Certificate Manager Service（新規）
        $this->app->singleton(GoogleCertificateManagerService::class, function ($app) {
            return new GoogleCertificateManagerService();
        });

        // ACME Service（既存）
        $this->app->singleton(AcmeService::class, function ($app) {
            return new AcmeService();
        });

        // Certificate Provider Factory（新規）
        $this->app->singleton(CertificateProviderFactory::class, function ($app) {
            return new CertificateProviderFactory(
                $app->make(GoGetSSLService::class),
                $app->make(GoogleCertificateManagerService::class)
            );
        });

        // Enhanced SSL SaaS Service（新規）
        $this->app->singleton(EnhancedSSLSaaSService::class, function ($app) {
            return new EnhancedSSLSaaSService(
                $app->make(SquareClient::class),
                $app->make(CertificateProviderFactory::class)
            );
        });

        // 互換性のため、既存のSSLSaaSServiceも登録
        $this->app->bind(\App\Services\SSLSaaSService::class, function ($app) {
            return new \App\Services\SSLSaaSService(
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
        // 本番環境でのみ設定の妥当性をチェック
        if ($this->app->environment('production')) {
            $this->validateConfigurations();
        }

        // SSL設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/../../config/ssl-providers.php', 'ssl.providers'
        );

        // Google Services設定をマージ
        $this->mergeConfigFrom(
            __DIR__.'/../../config/google-services.php', 'services.google'
        );

        // コマンドを登録
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\TestSquareConnection::class,
                \App\Console\Commands\TestGoGetSSLConnectionCommand::class,
                \App\Console\Commands\GetAllSslOrdersCommand::class,
                \App\Console\Commands\TestGoogleCertificateManagerCommand::class,
                \App\Console\Commands\CompareSSLProvidersCommand::class,
            ]);
        }
    }

    /**
     * Validate configurations for production
     */
    private function validateConfigurations(): void
    {
        // Square設定の検証
        $squareErrors = SquareConfigService::validateConfig();
        if (!empty($squareErrors)) {
            throw new \RuntimeException('Square configuration errors: ' . implode(', ', $squareErrors));
        }

        // SSL プロバイダー設定の検証
        $this->validateSSLProviderConfigs();
    }

    /**
     * Validate SSL provider configurations
     */
    private function validateSSLProviderConfigs(): void
    {
        $providers = config('ssl.providers', []);
        $errors = [];

        // GoGetSSLの設定チェック
        if (($providers['gogetssl']['enabled'] ?? false)) {
            if (empty(config('services.gogetssl.username'))) {
                $errors[] = 'GoGetSSL username is required when enabled';
            }
            if (empty(config('services.gogetssl.password'))) {
                $errors[] = 'GoGetSSL password is required when enabled';
            }
        }

        // Google Certificate Managerの設定チェック
        if (($providers['google_certificate_manager']['enabled'] ?? false)) {
            if (empty(config('services.google.project_id'))) {
                $errors[] = 'Google Cloud project ID is required when Google Certificate Manager is enabled';
            }
            
            $hasKeyFile = !empty(config('services.google.key_file_path')) && 
                         file_exists(config('services.google.key_file_path'));
            $hasKeyData = !empty(config('services.google.key_data'));
            
            if (!$hasKeyFile && !$hasKeyData) {
                $errors[] = 'Google service account credentials are required when Google Certificate Manager is enabled';
            }
        }

        if (!empty($errors)) {
            throw new \RuntimeException('SSL Provider configuration errors: ' . implode(', ', $errors));
        }
    }

    /**
     * Get package providers that should be registered
     */
    public function provides(): array
    {
        return [
            SquareClient::class,
            GoGetSSLService::class,
            GoogleCertificateManagerService::class,
            AcmeService::class,
            CertificateProviderFactory::class,
            EnhancedSSLSaaSService::class,
            \App\Services\SSLSaaSService::class,
        ];
    }
}