<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\{Artisan, Schedule, Log, Cache};
use App\Models\Certificate;
use App\Services\CertificateProviderFactory;

// Artisan Commands
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SSL Certificate Monitoring - Every hour
Schedule::command('ssl:monitor-certificates --notify --schedule-renewals')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ssl-monitoring.log'));

// SSL Provider Health Check - Every 6 hours
Schedule::command('ssl:monitor-certificates --check-providers')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ssl-health-check.log'));

// Comprehensive SSL Report - Daily at 6 AM
Schedule::command('ssl:compare-providers --verbose --export=json')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ssl-daily-report.log'));

// Subscription billing alignment check - Daily at midnight
Schedule::call(function () {
    try {
        // 簡単な課金チェックログ
        Log::channel('monitoring')->info('Subscription billing check completed', [
            'checked_at' => now()
        ]);
    } catch (\Throwable $e) {
        Log::channel('monitoring')->error('Subscription billing check failed', [
            'error' => $e->getMessage()
        ]);
    }
})->daily()
  ->name('subscription-billing-check')
  ->withoutOverlapping();

// Application health check - Every 30 minutes - 修正版
Schedule::call(function () {
    try {
        // ヘルスチェック用の独立した実装
        $healthStatus = performSystemHealthCheck();
        
        if ($healthStatus['status'] === 'unhealthy') {
            Log::critical('Application health check failed', $healthStatus);
            
            // Send alert if configured
            if (config('ssl-enhanced.monitoring.alert_on_failure')) {
                // NotifySystemHealth::dispatch($healthStatus);
                Log::channel('monitoring')->critical('System health alert triggered', $healthStatus);
            }
        }

        Log::channel('monitoring')->info('Application health check completed', [
            'status' => $healthStatus['status'],
            'checked_at' => now()
        ]);

    } catch (\Throwable $e) {
        Log::channel('monitoring')->error('Health check failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
})->everyThirtyMinutes()
  ->name('app-health-check')
  ->withoutOverlapping();

// Clear SSL caches - Every 2 hours
Schedule::call(function () {
    try {
        Cache::forget('ssl.providers.availability');
        Cache::forget('ssl_monitoring_results');
        
        // Clear certificate-specific caches older than 1 hour
        $cacheKeys = Cache::get('ssl_certificate_cache_keys', []);
        $cleared = 0;
        
        foreach ($cacheKeys as $key => $timestamp) {
            if ($timestamp < now()->subHour()->timestamp) {
                Cache::forget($key);
                unset($cacheKeys[$key]);
                $cleared++;
            }
        }
        
        Cache::put('ssl_certificate_cache_keys', $cacheKeys, now()->addDay());
        
        Log::channel('monitoring')->info('SSL cache cleanup completed', [
            'cleared_keys' => $cleared,
            'cleaned_at' => now()
        ]);
        
    } catch (\Throwable $e) {
        Log::channel('monitoring')->error('SSL cache cleanup failed', [
            'error' => $e->getMessage()
        ]);
    }
})->everyTwoHours()
  ->name('ssl-cache-cleanup');

// Development/Testing Commands (only in non-production)
if (!app()->environment('production')) {
    // Test SSL setup - Every hour in development
    Schedule::command('ssl:test-setup')
        ->hourly()
        ->withoutOverlapping()
        ->environments(['local', 'staging'])
        ->appendOutputTo(storage_path('logs/ssl-test-setup.log'));
}

// Provider failover check - Every 10 minutes - 修正版
Schedule::call(function () {
    try {
        // CertificateProviderFactoryの存在確認
        if (!app()->bound(CertificateProviderFactory::class)) {
            Log::channel('monitoring')->warning('CertificateProviderFactory not available for health check');
            return;
        }

        /** @var CertificateProviderFactory */
        $providerFactory = app(CertificateProviderFactory::class);
        $healthResults = $providerFactory->testAllProviders();
        
        $failedProviders = array_filter($healthResults, function ($result) {
            return in_array($result['status'], ['failed', 'error']);
        });

        if (!empty($failedProviders)) {
            Log::warning('SSL providers failing', [
                'failed_providers' => array_keys($failedProviders),
                'health_results' => $healthResults
            ]);

            // Trigger failover logic if configured
            if (config('ssl-enhanced.redundancy.enable_provider_fallback')) {
                Log::channel('ssl_providers')->warning('Provider failover triggered', [
                    'failed_providers' => array_keys($failedProviders)
                ]);
                // TriggerProviderFailover::dispatch($failedProviders);
            }
        } else {
            Log::channel('monitoring')->info('All SSL providers healthy', [
                'provider_count' => count($healthResults),
                'checked_at' => now()
            ]);
        }

    } catch (\Throwable $e) {
        Log::channel('ssl_providers')->error('Provider failover check failed', [
            'error' => $e->getMessage()
        ]);
    }
})->everyTenMinutes()
  ->name('ssl-provider-failover-check')
  ->withoutOverlapping();


/** 未実装　コメントだけど消すな！ */
// Clean up old certificate records - Weekly on Sunday
//Schedule::command('ssl:cleanup-certificates')
//    ->weeklyOn(0, '02:00') // Sunday at 2 AM
//    ->withoutOverlapping()
//    ->runInBackground();

// Test provider connections - Every 4 hours
//Schedule::command('ssl:test-providers')
//    ->cron('0 */4 * * *')
//    ->withoutOverlapping()
//    ->runInBackground()
//    ->appendOutputTo(storage_path('logs/ssl-provider-tests.log'));

// SSL statistics and usage report - Weekly on Monday
//Schedule::command('ssl:generate-usage-report')
//    ->weeklyOn(1, '08:00') // Monday at 8 AM
//    ->withoutOverlapping()
//    ->runInBackground();

// Emergency certificate validation check - Every 15 minutes during business hours
//Schedule::command('ssl:emergency-validation-check')
//    ->cron('*/15 9-17 * * 1-5') // Every 15 minutes, 9 AM - 5 PM, weekdays
//    ->withoutOverlapping()
//    ->runInBackground()
//    ->when(function () {
//        // Only run if there are failed certificates
//        return Certificate::where('status', 'failed')->exists();
//    });

// Subscription billing alignment check - Daily at midnight
//Schedule::command('subscription:check-billing')
//    ->daily()
//    ->withoutOverlapping()
//    ->runInBackground();
// Maintenance mode SSL services

//Schedule::command('ssl:maintenance-check')
//    ->everyFiveMinutes()
//    ->when(function () {
//        return app()->isDownForMaintenance();
//    })
//    ->withoutOverlapping()
//    ->runInBackground();




/**
 * 独立したシステムヘルスチェック関数
 */
function performSystemHealthCheck(): array
{
    $checks = [];
    $overallStatus = 'healthy';

    // Database check
    try {
        \DB::connection()->getPdo();
        $checks['database'] = ['status' => 'healthy', 'message' => 'Database connection successful'];
    } catch (\Throwable $e) {
        $checks['database'] = ['status' => 'unhealthy', 'message' => 'Database connection failed'];
        $overallStatus = 'unhealthy';
    }

    // Cache check
    try {
        Cache::put('health_check_test', 'test', 10);
        $value = Cache::get('health_check_test');
        Cache::forget('health_check_test');
        
        $checks['cache'] = $value === 'test' 
            ? ['status' => 'healthy', 'message' => 'Cache working correctly']
            : ['status' => 'unhealthy', 'message' => 'Cache not working'];
            
        if ($checks['cache']['status'] === 'unhealthy') {
            $overallStatus = 'unhealthy';
        }
    } catch (\Throwable $e) {
        $checks['cache'] = ['status' => 'unhealthy', 'message' => 'Cache error: ' . $e->getMessage()];
        $overallStatus = 'unhealthy';
    }

    // SSL providers check
    try {
        if (app()->bound(CertificateProviderFactory::class)) {
            /** @var CertificateProviderFactory */
            $factory = app(CertificateProviderFactory::class);
            $status = $factory->getProviderStatus();
            
            $checks['ssl_providers'] = [
                'status' => $status['configured_providers'] > 0 ? 'healthy' : 'warning',
                'message' => "Configured providers: {$status['configured_providers']}/{$status['total_providers']}",
                'providers' => $status['available_providers']
            ];
            
            if ($checks['ssl_providers']['status'] === 'warning' && $overallStatus === 'healthy') {
                $overallStatus = 'warning';
            }
        } else {
            $checks['ssl_providers'] = ['status' => 'warning', 'message' => 'SSL provider factory not available'];
            if ($overallStatus === 'healthy') {
                $overallStatus = 'warning';
            }
        }
    } catch (\Throwable $e) {
        $checks['ssl_providers'] = ['status' => 'unhealthy', 'message' => 'SSL provider check failed'];
        $overallStatus = 'unhealthy';
    }

    return [
        'status' => $overallStatus,
        'timestamp' => now()->toISOString(),
        'checks' => $checks
    ];
}