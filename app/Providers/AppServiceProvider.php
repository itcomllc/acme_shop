<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{DB, Log, Cache};
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Development environment specific registrations
        if ($this->app->environment('local', 'testing')) {
            $this->registerDevelopmentServices();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure database query logging in development
        if ($this->app->environment('local') && config('app.debug')) {
            $this->enableQueryLogging();
        }

        // Setup global application settings
        $this->setupGlobalSettings();

        // Register custom validation rules
        $this->registerValidationRules();

        // Setup application health checks
        $this->setupHealthChecks();
    }

    /**
     * Register development-specific services
     */
    private function registerDevelopmentServices(): void
    {
        // Register development tools, debug providers, etc.
        //if (class_exists(IdeHelperServiceProvider::class)) {
        //    $this->app->register(IdeHelperServiceProvider::class);
        //}
    }

    /**
     * Enable query logging for development
     */
    private function enableQueryLogging(): void
    {
        // データベース接続チェック
        if (!$this->isDatabaseAvailable()) {
            return;
        }

        DB::listen(function ($query) {
            try {
                // 通常のログチャンネルを使用（databaseではなく）
                Log::info('Database Query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms'
                ]);
            } catch (\Exception $e) {
                // ログ記録に失敗した場合は無視
            }
        });
    }

    /**
     * Setup global application settings
     */
    private function setupGlobalSettings(): void
    {
        // Set default timezone if not already set
        if (!config('app.timezone')) {
            config(['app.timezone' => 'UTC']);
        }

        // Setup default cache settings
        $this->setupCacheSettings();

        // Setup SSL certificate validation settings
        $this->setupSSLSettings();
    }

    /**
     * Setup cache-related settings
     */
    private function setupCacheSettings(): void
    {
        // Set default cache TTL for different types of data
        config([
            'cache.ssl_providers_ttl' => 3600, // 1 hour
            'cache.certificate_status_ttl' => 600, // 10 minutes
            'cache.subscription_data_ttl' => 1800, // 30 minutes
        ]);
    }

    /**
     * Setup SSL-related application settings
     */
    private function setupSSLSettings(): void
    {
        // Merge SSL configuration from enhanced config
        if (config('ssl-enhanced')) {
            config([
                'ssl.default_provider' => config('ssl-enhanced.default_provider'),
                'ssl.certificate.auto_renewal_days' => config('ssl-enhanced.certificate.auto_renewal_days'),
                'ssl.health_check.interval' => config('ssl-enhanced.health_check.interval'),
            ]);
        }

        // Setup SSL verification for HTTP client
        if (app()->environment('production')) {
            config([
                'http.verify' => true,
                'http.timeout' => config('ssl-enhanced.provider_timeout', 30),
            ]);
        }
    }

    /**
     * Register custom validation rules
     */
    private function registerValidationRules(): void
    {
        // Domain validation rule
        \Illuminate\Support\Facades\Validator::extend('ssl_domain', function ($attribute, $value, $parameters, $validator) {
            // Validate domain format for SSL certificates
            if (str_starts_with($value, '*.')) {
                // Wildcard domain validation
                $domain = substr($value, 2);
                return preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $domain);
            }
            
            // Regular domain validation
            return preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $value);
        });

        // SSL provider validation rule
        \Illuminate\Support\Facades\Validator::extend('ssl_provider', function ($attribute, $value, $parameters, $validator) {
            $validProviders = ['gogetssl', 'google_certificate_manager', 'lets_encrypt'];
            return in_array($value, $validProviders);
        });

        // Certificate type validation rule
        \Illuminate\Support\Facades\Validator::extend('certificate_type', function ($attribute, $value, $parameters, $validator) {
            $validTypes = ['DV', 'OV', 'EV'];
            return in_array($value, $validTypes);
        });
    }

    /**
     * Setup application health checks
     */
    private function setupHealthChecks(): void
    {
        // Register health check routes for monitoring
        $this->app->singleton('health.checks', function ($app) {
            return [
                'database' => function () {
                    try {
                        DB::connection()->getPdo();
                        return ['status' => 'healthy', 'message' => 'Database connection successful'];
                    } catch (\Exception $e) {
                        return ['status' => 'unhealthy', 'message' => 'Database connection failed: ' . $e->getMessage()];
                    }
                },
                'cache' => function () {
                    try {
                        Cache::put('health_check', 'test', 10);
                        $value = Cache::get('health_check');
                        Cache::forget('health_check');
                        return $value === 'test' 
                            ? ['status' => 'healthy', 'message' => 'Cache working correctly']
                            : ['status' => 'unhealthy', 'message' => 'Cache not working'];
                    } catch (\Exception $e) {
                        return ['status' => 'unhealthy', 'message' => 'Cache error: ' . $e->getMessage()];
                    }
                },
                'ssl_providers' => function () {
                    try {
                        // CertificateProviderFactoryが存在する場合のみ実行
                        if (class_exists(\App\Services\CertificateProviderFactory::class)) {
                            $factory = app(\App\Services\CertificateProviderFactory::class);
                            $status = $factory->getProviderStatus();
                            return [
                                'status' => $status['configured_providers'] > 0 ? 'healthy' : 'warning',
                                'message' => "Configured providers: {$status['configured_providers']}/{$status['total_providers']}",
                                'providers' => $status['available_providers']
                            ];
                        }
                        return ['status' => 'warning', 'message' => 'SSL provider factory not available'];
                    } catch (\Exception $e) {
                        return ['status' => 'unhealthy', 'message' => 'SSL provider check failed: ' . $e->getMessage()];
                    }
                }
            ];
        });
    }

    /**
     * Check if database is available
     */
    private function isDatabaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get application health status
     */
    public function getHealthStatus(): array
    {
        $checks = app('health.checks');
        $results = [];
        $overallStatus = 'healthy';

        foreach ($checks as $name => $check) {
            try {
                $result = $check();
                $results[$name] = $result;
                
                if ($result['status'] === 'unhealthy') {
                    $overallStatus = 'unhealthy';
                } elseif ($result['status'] === 'warning' && $overallStatus === 'healthy') {
                    $overallStatus = 'warning';
                }
            } catch (\Exception $e) {
                $results[$name] = [
                    'status' => 'unhealthy',
                    'message' => 'Health check failed: ' . $e->getMessage()
                ];
                $overallStatus = 'unhealthy';
            }
        }

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'checks' => $results
        ];
    }
}