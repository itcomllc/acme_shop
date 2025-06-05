<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Certificate, Subscription};
use App\Jobs\ScheduleCertificateRenewal;
use App\Events\CertificateExpiring;
use App\Services\EnhancedSSLSaaSService;
use App\Notifications\SSLSystemAlertNotification;
use Illuminate\Support\Facades\{Log, Cache, Notification};

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

        // 構造化ログ
        Log::info('SSL Certificate Monitoring started', [
            'command' => 'ssl:monitor-certificates',
            'options' => $this->options()
        ]);

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

            // 異常検知とSlack通知
            $this->detectAnomaliesAndNotify($stats);

            // Display summary
            $this->displaySummary($stats);

            // Update cache with monitoring results
            $this->cacheMonitoringResults($stats);

            // 構造化ログ
            Log::info('SSL Certificate Monitoring completed', array_merge($stats, [
                'status' => 'success'
            ]));

            $this->info('✅ Certificate monitoring completed successfully');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Certificate monitoring failed: ' . $e->getMessage());
            
            Log::error('Certificate monitoring command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 重大エラーのSlack通知
            $this->sendSlackAlert(
                'SSL Monitoring System Error',
                'SSL monitoring command failed with exception',
                [
                    'Error' => $e->getMessage(),
                    'File' => $e->getFile() . ':' . $e->getLine(),
                    'Command' => $this->signature
                ],
                'critical'
            );

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
        $providerDetails = [];

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
                $providerDetails[$provider] = $result['error'] ?? 'Unknown error';
                
                if ($this->option('verbose') && isset($result['error'])) {
                    $this->line("      Error: {$result['error']}");
                }
            }
        }

        return [
            'unhealthy_providers' => $unhealthyProviders,
            'provider_details' => $providerDetails,
            'health_results' => $healthResults
        ];
    }

    /**
     * 異常検知とSlack通知
     */
    private function detectAnomaliesAndNotify(array $stats): void
    {
        if (!config('ssl-enhanced.monitoring.alert_on_failure', true)) {
            return;
        }

        // 失敗した証明書の検知
        if ($stats['failed_certificates'] > 0) {
            $this->sendSlackAlert(
                'Certificate Failures Detected',
                "Found {$stats['failed_certificates']} failed certificates that require attention",
                [
                    'Failed Certificates' => $stats['failed_certificates'],
                    'Total Certificates' => $stats['total_certificates'],
                    'Failure Rate' => round(($stats['failed_certificates'] / max($stats['total_certificates'], 1)) * 100, 1) . '%'
                ],
                'error'
            );
        }

        // 大量の期限切れ間近証明書の検知
        $expiringThreshold = 10; // 設定可能にする
        if ($stats['expiring_certificates'] > $expiringThreshold) {
            $this->sendSlackAlert(
                'Many Certificates Expiring Soon',
                "Warning: {$stats['expiring_certificates']} certificates expiring within {$this->option('days')} days",
                [
                    'Expiring Soon' => $stats['expiring_certificates'],
                    'Auto Renewal Scheduled' => $stats['renewals_scheduled'],
                    'Threshold' => $expiringThreshold
                ],
                'warning'
            );
        }

        // プロバイダーヘルスの問題検知
        if ($stats['provider_issues'] > 0) {
            $this->sendSlackAlert(
                'SSL Provider Health Issues',
                "{$stats['provider_issues']} SSL providers are experiencing connectivity or health issues",
                [
                    'Unhealthy Providers' => $stats['provider_issues'],
                    'Impact' => 'New certificate issuance may be affected'
                ],
                'critical'
            );
        }

        // 成功時の定期報告（1日1回）
        if ($this->shouldSendDailyReport($stats)) {
            $this->sendSlackAlert(
                'SSL System Daily Report',
                'SSL monitoring system is running normally',
                [
                    'Total Certificates' => $stats['total_certificates'],
                    'Expiring Soon' => $stats['expiring_certificates'],
                    'Failed Certificates' => $stats['failed_certificates'],
                    'Renewals Scheduled' => $stats['renewals_scheduled']
                ],
                'success'
            );
        }
    }

    /**
     * Slack通知送信
     */
    private function sendSlackAlert(string $title, string $message, array $details = [], string $severity = 'warning'): void
    {
        try {
            if (!config('services.slack.notifications.webhook_url')) {
                Log::warning('Slack webhook URL not configured, skipping alert', [
                    'title' => $title,
                    'severity' => $severity
                ]);
                return;
            }

            Notification::route('slack', config('services.slack.notifications.webhook_url'))
                ->notify(new SSLSystemAlertNotification($title, $message, $details, $severity));
                
            Log::info('Slack alert sent successfully', [
                'title' => $title,
                'severity' => $severity,
                'details_count' => count($details)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', [
                'error' => $e->getMessage(),
                'title' => $title,
                'severity' => $severity
            ]);
        }
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

    /**
     * 1日1回の定期報告が必要かチェック
     */
    private function shouldSendDailyReport(array $stats): bool
    {
        // 毎日6時にのみ送信（設定可能）
        $reportHour = config('ssl-enhanced.monitoring.daily_report_hour', 6);
        if (now()->hour !== $reportHour) {
            return false;
        }

        // 今日既に送信済みかチェック
        $lastReportDate = Cache::get('ssl_daily_report_last_sent');
        if ($lastReportDate && $lastReportDate === now()->toDateString()) {
            return false;
        }

        // 報告送信をマーク
        Cache::put('ssl_daily_report_last_sent', now()->toDateString(), now()->addDays(2));
        
        return true;
    }
}