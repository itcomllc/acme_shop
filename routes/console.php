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

// Application health check - Every 30 minutes
Schedule::call(function () {
    $healthStatus = app(\App\Providers\AppServiceProvider::class)->getHealthStatus();
    
    if ($healthStatus['status'] === 'unhealthy') {
        Log::critical('Application health check failed', $healthStatus);
        
        // Send alert if configured
        if (config('ssl-enhanced.monitoring.alert_on_failure')) {
            // NotifySystemHealth::dispatch($healthStatus);
        }
    }
})->everyThirtyMinutes()
  ->name('app-health-check')
  ->withoutOverlapping();

// Clear SSL caches - Every 2 hours
Schedule::call(function () {
    Cache::forget('ssl.providers.availability');
    Cache::forget('ssl_monitoring_results');
    
    // Clear certificate-specific caches older than 1 hour
    $cacheKeys = Cache::get('ssl_certificate_cache_keys', []);
    foreach ($cacheKeys as $key => $timestamp) {
        if ($timestamp < now()->subHour()->timestamp) {
            Cache::forget($key);
            unset($cacheKeys[$key]);
        }
    }
    Cache::put('ssl_certificate_cache_keys', $cacheKeys, now()->addDay());
    
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

// Maintenance mode SSL services
//Schedule::command('ssl:maintenance-check')
//    ->everyFiveMinutes()
//    ->when(function () {
//        return app()->isDownForMaintenance();
//    })
//    ->withoutOverlapping()
//    ->runInBackground();

// Provider failover check - Every 10 minutes
Schedule::call(function () {
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
            // TriggerProviderFailover::dispatch($failedProviders);
        }
    }
})->everyTenMinutes()
  ->name('ssl-provider-failover-check')
  ->withoutOverlapping();