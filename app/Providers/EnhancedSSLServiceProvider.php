<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Log, Cache};
use App\Services\{
    EnhancedSSLSaaSService, 
    SquareConfigService, 
    GoGetSSLService, 
    GoogleCertificateManagerService,
    AcmeService, 
    CertificateProviderFactory
};
use Square\SquareClient;

/**
 * Enhanced SSL Service Provider
 * 
 * Provides enhanced SSL certificate management services with multi-provider support
 */
class EnhancedSSLServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Square Client (singleton)
        $this->app->singleton(SquareClient::class, function ($app) {
            return SquareConfigService::createClient();
        });

        // Register Certificate Provider Factory (singleton)
        $this->app->singleton(CertificateProviderFactory::class, function ($app) {
            return new CertificateProviderFactory();
        });

        // Register individual certificate providers
        $this->registerCertificateProviders();

        // Register ACME Service (singleton)
        $this->app->singleton(AcmeService::class, function ($app) {
            return new AcmeService();
        });

        // Register Enhanced SSL SaaS Service (singleton)
        $this->app->singleton(EnhancedSSLSaaSService::class, function ($app) {
            return new EnhancedSSLSaaSService(
                $app->make(SquareClient::class),
                $app->make(CertificateProviderFactory::class),
                $app->make(AcmeService::class)
            );
        });

        // Register service aliases for backward compatibility
        $this->app->alias(EnhancedSSLSaaSService::class, 'ssl.enhanced');
        $this->app->alias(CertificateProviderFactory::class, 'ssl.provider.factory');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Merge package configuration
        $this->mergePackageConfig();

        // Register configuration publishing
        $this->registerConfigPublishing();

        // Register package resources
        $this->registerResources();

        // Validate configurations in production
        if ($this->app->environment('production')) {
            $this->validateProductionConfiguration();
        }

        // Cache provider availability for performance
        $this->cacheProviderAvailability();

        // Register SSL-related commands
        $this->registerCommands();

        // Setup health checks
        $this->setupHealthChecks();

        // Register event listeners
        $this->registerEventListeners();

        // Register middleware
        $this->registerMiddleware();

        // Log service initialization
        Log::info('Enhanced SSL Service Provider booted', [
            'environment' => $this->app->environment(),
            'providers_available' => $this->getAvailableProviders(),
            'version' => $this->getVersion()
        ]);
    }

    /**
     * Register individual certificate providers
     */
    private function registerCertificateProviders(): void
    {
        // GoGetSSL Service
        $this->app->singleton(GoGetSSLService::class, function ($app) {
            return new GoGetSSLService();
        });

        // Google Certificate Manager Service
        $this->app->singleton(GoogleCertificateManagerService::class, function ($app) {
            return new GoogleCertificateManagerService();
        });

        // Register provider-specific bindings
        $this->app->bind('ssl.provider.gogetssl', function ($app) {
            return $app->make(GoGetSSLService::class);
        });

        $this->app->bind('ssl.provider.google', function ($app) {
            return $app->make(GoogleCertificateManagerService::class);
        });
    }

    /**
     * Validate production configuration
     */
    private function validateProductionConfiguration(): void
    {
        $errors = [];

        // Validate Square configuration
        $squareErrors = SquareConfigService::validateConfig();
        if (!empty($squareErrors)) {
            $errors = array_merge($errors, $squareErrors);
        }

        // Validate SSL provider configurations
        /** @var CertificateProviderFactory */
        $factory = $this->app->make(CertificateProviderFactory::class);
        
        $providers = [
            CertificateProviderFactory::PROVIDER_GOGETSSL,
            CertificateProviderFactory::PROVIDER_GOOGLE_CERTIFICATE_MANAGER
        ];

        foreach ($providers as $provider) {
            $providerErrors = $factory->validateProviderConfig($provider);
            if (!empty($providerErrors)) {
                $errors = array_merge($errors, array_map(
                    fn($error) => "{$provider}: {$error}",
                    $providerErrors
                ));
            }
        }

        // Throw exception if any critical errors found
        if (!empty($errors)) {
            throw new \RuntimeException('SSL service configuration errors: ' . implode(', ', $errors));
        }
    }

    /**
     * Cache provider availability for performance
     */
    private function cacheProviderAvailability(): void
    {
        $cacheKey = 'ssl.providers.availability';
        
        // Check if already cached
        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            /** @var CertificateProviderFactory */
            $factory = $this->app->make(CertificateProviderFactory::class);
            $availability = $factory->getProviderStatus();
            
            // Cache for 1 hour
            Cache::put($cacheKey, $availability, now()->addHour());
            
        } catch (\Exception $e) {
            Log::warning('Failed to cache provider availability', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Register SSL-related commands
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\TestGoGetSSLConnectionCommand::class,
                \App\Console\Commands\TestGoogleCertificateManagerCommand::class,
                \App\Console\Commands\CompareSSLProvidersCommand::class,
                \App\Console\Commands\GetAllSslOrdersCommand::class,
                \App\Console\Commands\TestSquareConnection::class,
                \App\Console\Commands\MonitorCertificatesCommand::class,
            ]);
        }
    }

    /**
     * Setup health checks
     */
    private function setupHealthChecks(): void
    {
        // Register health check routes if needed
        // This could be expanded to include automated health monitoring
        
        $this->app->singleton('ssl.health', function ($app) {
            return function () use ($app) {
                /** @var EnhancedSSLSaaSService */
                $sslService = $app->make(EnhancedSSLSaaSService::class);
                return $sslService->performHealthCheck();
            };
        });
    }

    /**
     * Get list of available providers
     */
    private function getAvailableProviders(): array
    {
        try {
            /** @var CertificateProviderFactory */
            $factory = $this->app->make(CertificateProviderFactory::class);
            return $factory->getProviderStatus()['available_providers'];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Register event listeners for SSL-related events
     */
    private function registerEventListeners(): void
    {
        // Listen for certificate issued events
        $this->app['events']->listen(
            \App\Events\CertificateIssued::class,
            function ($event) {
                Log::info('Certificate issued', [
                    'certificate_id' => $event->certificate->id,
                    'domain' => $event->certificate->domain,
                    'provider' => $event->certificate->provider
                ]);
            }
        );

        // Listen for certificate renewal events
        $this->app['events']->listen(
            \App\Events\CertificateRenewed::class,
            function ($event) {
                Log::info('Certificate renewed', [
                    'old_certificate_id' => $event->oldCertificate->id,
                    'new_certificate_id' => $event->newCertificate->id,
                    'domain' => $event->newCertificate->domain,
                    'provider' => $event->newCertificate->provider
                ]);
            }
        );

        // Listen for provider health check events
        $this->app['events']->listen(
            \App\Events\ProviderHealthCheckFailed::class,
            function ($event) {
                Log::error('Provider health check failed', [
                    'provider' => $event->provider,
                    'error' => $event->error
                ]);
            }
        );
    }

    /**
     * Register configuration publishing
     */
    private function registerConfigPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/ssl-enhanced.php' => config_path('ssl-enhanced.php'),
            ], 'ssl-enhanced-config');
        }
    }

    /**
     * Register middleware
     */
    private function registerMiddleware(): void
    {
        // Register SSL-related middleware if needed
        // This could include rate limiting, provider health checks, etc.
    }

    /**
     * Merge package configuration
     */
    private function mergePackageConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/ssl-enhanced.php',
            'ssl-enhanced'
        );
    }

    /**
     * Register package resources
     */
    private function registerResources(): void
    {
        // Register views, translations, etc. if needed
        if (is_dir(__DIR__.'/../../resources/views')) {
            $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ssl-enhanced');
        }
    }

    /**
     * Check if provider is available
     */
    private function isProviderAvailable(string $provider): bool
    {
        try {
            /** @var CertificateProviderFactory */
            $factory = $this->app->make(CertificateProviderFactory::class);
            $errors = $factory->validateProviderConfig($provider);
            return empty($errors);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get package version
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Provides the services this provider provides
     * 
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            EnhancedSSLSaaSService::class,
            CertificateProviderFactory::class,
            GoGetSSLService::class,
            GoogleCertificateManagerService::class,
            AcmeService::class,
            'ssl.enhanced',
            'ssl.provider.factory',
            'ssl.provider.gogetssl',
            'ssl.provider.google',
            'ssl.health'
        ];
    }
}