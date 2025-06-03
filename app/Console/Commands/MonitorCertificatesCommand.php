<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Certificate, Subscription};
use App\Jobs\ScheduleCertificateRenewal;
use App\Events\CertificateExpiring;
use App\Services\EnhancedSSLSaaSService;
use Illuminate\Support\Facades\{Log, Cache};

class MonitorCertificatesCommand extends Command
{
    protected $signature = 'ssl:monitor-certificates 
                            {--force : Force monitoring even if recently run}
                            {--notify : Send notifications for expiring certificates}
                            {--schedule-renewals : Schedule automatic renewals}
                            {--check-providers : Check provider health}
                            {--days=30 : Days before expiry to consider as expiring}';

    protected $description = 'Monitor SSL certificates and schedule renewals';

    public function __construct(
        private readonly EnhancedSSLSaaSService $sslService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Starting SSL Certificate Monitoring');
        $this->newLine();

        // Check if monitoring was recently run (prevent duplicate runs)
        if (!$this->option('force') && $this->wasRecentlyRun()) {
            $this->info('⏭️  Monitoring was recently run. Use --force to override.');
            return self::SUCCESS;
        }

        try {
            // Mark as running
            $this->markAsRunning();

            $stats = [
                'total_certificates' => 0,
                'expiring_certificates' => 0,
                'failed_certificates' => 0,
                'renewals_scheduled' => 0,
                'notifications_sent' => 0,
                'provider_issues' => 0
            ];

            // Monitor active certificates
            $stats = array_merge($stats, $this->monitorActiveCertificates());

            // Check expiring certificates
            if ($this->option('notify') || $this->option('schedule-renewals')) {
                $expiringStats = $this->handleExpiringCertificates();
                $stats = array_merge($stats, $expiringStats);
            }

            // Check provider health
            if ($this->option('check-providers')) {
                $providerStats = $this->checkProviderHealth();
                $stats['provider_issues'] = $providerStats['unhealthy_providers'];
            }

            // Display summary
            $this->displaySummary($stats);

            // Update cache with monitoring results
            $this->cacheMonitoringResults($stats);

            $this->info('✅ Certificate monitoring completed successfully');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Certificate monitoring failed: ' . $e->getMessage());
            
            Log::error('Certificate monitoring command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        } finally {
            $this->markAsCompleted();
        }
    }

    /**
     * Monitor active certificates
     */
    private function monitorActiveCertificates(): array
    {
        $this->info('📋 Monitoring active certificates...');
        
        $activeCertificates = Certificate::active()->get();
        $failedCertificates = Certificate::where('status', Certificate::STATUS_FAILED)->count();
        
        $stats = [
            'total_certificates' => $activeCertificates->count(),
            'failed_certificates' => $failedCertificates
        ];

        if ($this->option('verbose')) {
            $this->table(
                ['Domain', 'Provider', 'Expires', 'Days Left', 'Status'],
                $activeCertificates->take(10)->map(function ($cert) {
                    return [
                        $cert->domain,
                        $cert->getProviderDisplayName(),
                        $cert->expires_at?->format('M j, Y'),
                        $cert->getDaysUntilExpiration(),
                        $cert->getStatusDisplayName()
                    ];
                })->toArray()
            );

            if ($activeCertificates->count() > 10) {
                $this->line("... and " . ($activeCertificates->count() - 10) . " more certificates");
            }
        }

        $this->line("  📊 Active certificates: {$stats['total_certificates']}");
        $this->line("  ❌ Failed certificates: {$stats['failed_certificates']}");

        return $stats;
    }

    /**
     * Handle expiring certificates
     */
    private function handleExpiringCertificates(): array
    {
        $days = (int) $this->option('days');
        $this->info("📅 Checking certificates expiring within {$days} days...");

        $expiringCertificates = Certificate::expiring($days)->get();
        $stats = [
            'expiring_certificates' => $expiringCertificates->count(),
            'renewals_scheduled' => 0,
            'notifications_sent' => 0
        ];

        foreach ($expiringCertificates as $certificate) {
            $daysLeft = $certificate->getDaysUntilExpiration();
            
            if ($this->option('verbose')) {
                $this->line("  ⚠️  {$certificate->domain} expires in {$daysLeft} days");
            }

            // Schedule renewal if requested
            if ($this->option('schedule-renewals') && $this->shouldScheduleRenewal($certificate)) {
                $this->scheduleRenewal($certificate);
                $stats['renewals_scheduled']++;
            }

            // Send notifications if requested
            if ($this->option('notify')) {
                $this->sendExpirationNotification($certificate, $daysLeft);
                $stats['notifications_sent']++;
            }
        }

        $this->line("  ⏰ Expiring certificates: {$stats['expiring_certificates']}");
        
        if ($this->option('schedule-renewals')) {
            $this->line("  🔄 Renewals scheduled: {$stats['renewals_scheduled']}");
        }

        if ($this->option('notify')) {
            $this->line("  📧 Notifications sent: {$stats['notifications_sent']}");
        }

        return $stats;
    }

    /**
     * Check provider health
     */
    private function checkProviderHealth(): array
    {
        $this->info('🏥 Checking provider health...');

        $healthResults = $this->sslService->performHealthCheck();
        $unhealthyProviders = 0;

        foreach ($healthResults as $provider => $result) {
            $status = $result['status'] ?? 'unknown';
            $icon = match($status) {
                'connected' => '✅',
                'failed', 'error' => '❌',
                default => '❓'
            };

            $this->line("  {$icon} {$provider}: {$status}");
            
            if (in_array($status, ['failed', 'error'])) {
                $unhealthyProviders++;
                if ($this->option('verbose') && isset($result['error'])) {
                    $this->line("      Error: {$result['error']}");
                }
            }
        }

        return ['unhealthy_providers' => $unhealthyProviders];
    }

    /**
     * Check if certificate renewal should be scheduled
     */
    private function shouldScheduleRenewal(Certificate $certificate): bool
    {
        // Don't schedule if subscription is not active
        if (!$certificate->subscription->isActive()) {
            return false;
        }

        // Don't schedule if auto-renewal is disabled
        if (!$certificate->subscription->auto_renewal_enabled) {
            return false;
        }

        // Don't schedule if already has a pending renewal
        $pendingRenewal = Certificate::where('subscription_id', $certificate->subscription_id)
            ->where('domain', $certificate->domain)
            ->where('status', Certificate::STATUS_PENDING)
            ->where('id', '!=', $certificate->id)
            ->exists();

        return !$pendingRenewal;
    }

    /**
     * Schedule certificate renewal
     */
    private function scheduleRenewal(Certificate $certificate): void
    {
        try {
            ScheduleCertificateRenewal::dispatch($certificate);
            
            Log::info('Certificate renewal scheduled via monitoring', [
                'certificate_id' => $certificate->id,
                'domain' => $certificate->domain,
                'expires_at' => $certificate->expires_at?->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            $this->warn("  ⚠️  Failed to schedule renewal for {$certificate->domain}: {$e->getMessage()}");
            
            Log::error('Failed to schedule certificate renewal', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send expiration notification
     */
    private function sendExpirationNotification(Certificate $certificate, int $daysLeft): void
    {
        try {
            CertificateExpiring::dispatch($certificate, $daysLeft);
            
            Log::info('Certificate expiration notification sent', [
                'certificate_id' => $certificate->id,
                'domain' => $certificate->domain,
                'days_left' => $daysLeft
            ]);

        } catch (\Exception $e) {
            $this->warn("  ⚠️  Failed to send notification for {$certificate->domain}: {$e->getMessage()}");
        }
    }

    /**
     * Display monitoring summary
     */
    private function displaySummary(array $stats): void
    {
        $this->newLine();
        $this->info('📊 Monitoring Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Certificates', $stats['total_certificates']],
                ['Expiring Certificates', $stats['expiring_certificates']],
                ['Failed Certificates', $stats['failed_certificates']],
                ['Renewals Scheduled', $stats['renewals_scheduled']],
                ['Notifications Sent', $stats['notifications_sent']],
                ['Provider Issues', $stats['provider_issues']]
            ]
        );

        // Health status indicators
        if ($stats['failed_certificates'] > 0) {
            $this->warn("⚠️  {$stats['failed_certificates']} certificates have failed - review required");
        }

        if ($stats['expiring_certificates'] > 0) {
            $this->warn("⏰ {$stats['expiring_certificates']} certificates expiring soon");
        }

        if ($stats['provider_issues'] > 0) {
            $this->error("🚨 {$stats['provider_issues']} providers have health issues");
        }

        if ($stats['failed_certificates'] == 0 && $stats['provider_issues'] == 0) {
            $this->info('🎉 All systems healthy!');
        }
    }

    /**
     * Check if monitoring was recently run
     */
    private function wasRecentlyRun(): bool
    {
        $lastRun = Cache::get('ssl_monitoring_last_run');
        if (!$lastRun) {
            return false;
        }

        $threshold = now()->subMinutes(30); // Don't run more than once per 30 minutes
        return $lastRun > $threshold;
    }

    /**
     * Mark monitoring as running
     */
    private function markAsRunning(): void
    {
        Cache::put('ssl_monitoring_running', true, now()->addHours(2));
        Cache::put('ssl_monitoring_last_run', now(), now()->addDay());
    }

    /**
     * Mark monitoring as completed
     */
    private function markAsCompleted(): void
    {
        Cache::forget('ssl_monitoring_running');
    }

    /**
     * Cache monitoring results
     */
    private function cacheMonitoringResults(array $stats): void
    {
        Cache::put('ssl_monitoring_results', array_merge($stats, [
            'last_run' => now()->toISOString(),
            'health_status' => $this->calculateHealthStatus($stats)
        ]), now()->addHours(24));
    }

    /**
     * Calculate overall health status
     */
    private function calculateHealthStatus(array $stats): string
    {
        if ($stats['provider_issues'] > 0 || $stats['failed_certificates'] > 0) {
            return 'critical';
        }

        if ($stats['expiring_certificates'] > 0) {
            return 'warning';
        }

        return 'healthy';
    }
}