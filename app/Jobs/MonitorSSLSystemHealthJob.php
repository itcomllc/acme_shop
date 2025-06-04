<?php

namespace App\Jobs;

use App\Models\{Certificate, Subscription, User};
use App\Services\{CertificateProviderFactory, EnhancedSSLSaaSService};
use App\Notifications\{CertificateExpiringNotification, SystemHealthIssueNotification};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Log, Cache, DB, Notification};
use Carbon\Carbon;

/**
 * Monitor SSL System Health Job
 * SSL システムの健全性を監視
 */
class MonitorSSLSystemHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $checkTypes;
    private bool $sendNotifications;
    private int $healthThreshold;

    public function __construct(
        array $checkTypes = ['providers', 'certificates', 'subscriptions', 'database'],
        bool $sendNotifications = true,
        int $healthThreshold = 80
    ) {
        $this->checkTypes = $checkTypes;
        $this->sendNotifications = $sendNotifications;
        $this->healthThreshold = $healthThreshold;
    }

    public function handle(
        CertificateProviderFactory $providerFactory,
        EnhancedSSLSaaSService $sslService
    ): void {
        try {
            Log::info('Starting SSL system health monitoring', [
                'check_types' => $this->checkTypes,
                'send_notifications' => $this->sendNotifications,
                'health_threshold' => $this->healthThreshold
            ]);

            $healthResults = [];
            $overallHealthScore = 100;
            $criticalIssues = [];
            $warnings = [];

            // 各種ヘルスチェックを実行
            foreach ($this->checkTypes as $checkType) {
                try {
                    $checkResult = $this->performHealthCheck($checkType, $providerFactory, $sslService);
                    $healthResults[$checkType] = $checkResult;
                    
                    // スコアの更新
                    $overallHealthScore = min($overallHealthScore, $checkResult['score']);
                    
                    // 問題の収集
                    if ($checkResult['status'] === 'critical') {
                        $criticalIssues = array_merge($criticalIssues, $checkResult['issues']);
                    } elseif ($checkResult['status'] === 'warning') {
                        $warnings = array_merge($warnings, $checkResult['issues']);
                    }

                } catch (\Exception $e) {
                    Log::error("Health check failed for {$checkType}", [
                        'error' => $e->getMessage()
                    ]);
                    
                    $healthResults[$checkType] = [
                        'status' => 'critical',
                        'score' => 0,
                        'message' => "Health check failed: {$e->getMessage()}",
                        'issues' => ["Health check system failure for {$checkType}"]
                    ];
                    
                    $overallHealthScore = 0;
                    $criticalIssues[] = "Health check system failure for {$checkType}";
                }
            }

            // 健全性結果をキャッシュ
            $healthSummary = [
                'overall_score' => $overallHealthScore,
                'overall_status' => $this->getOverallStatus($overallHealthScore),
                'check_results' => $healthResults,
                'critical_issues' => $criticalIssues,
                'warnings' => $warnings,
                'last_checked' => now()->toISOString()
            ];

            $this->cacheHealthResults($healthSummary);

            // 通知処理
            if ($this->sendNotifications) {
                $this->processNotifications($healthSummary);
            }

            // 自動修復可能な問題の処理
            $this->performAutoRemediation($healthResults);

            Log::info('SSL system health monitoring completed', [
                'overall_score' => $overallHealthScore,
                'overall_status' => $healthSummary['overall_status'],
                'critical_issues_count' => count($criticalIssues),
                'warnings_count' => count($warnings)
            ]);

        } catch (\Exception $e) {
            Log::error('SSL system health monitoring failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 各種ヘルスチェックを実行
     */
    private function performHealthCheck(
        string $checkType,
        CertificateProviderFactory $providerFactory,
        EnhancedSSLSaaSService $sslService
    ): array {
        return match ($checkType) {
            'providers' => $this->checkProviderHealth($providerFactory),
            'certificates' => $this->checkCertificateHealth(),
            'subscriptions' => $this->checkSubscriptionHealth(),
            'database' => $this->checkDatabaseHealth(),
            'queues' => $this->checkQueueHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
            default => [
                'status' => 'unknown',
                'score' => 0,
                'message' => "Unknown check type: {$checkType}",
                'issues' => ["Unknown health check type: {$checkType}"]
            ]
        };
    }

    /**
     * プロバイダーの健全性チェック
     */
    private function checkProviderHealth(CertificateProviderFactory $providerFactory): array
    {
        $issues = [];
        $warnings = [];
        $score = 100;

        try {
            $providerStatus = $providerFactory->getProviderStatus();
            $healthResults = $providerFactory->testAllProviders();

            $totalProviders = count($providerStatus['available_providers']);
            $healthyProviders = 0;

            foreach ($healthResults as $provider => $result) {
                if ($result['status'] === 'connected') {
                    $healthyProviders++;
                } elseif ($result['status'] === 'failed') {
                    $issues[] = "Provider {$provider} is not responding";
                } else {
                    $warnings[] = "Provider {$provider} has connectivity issues";
                }
            }

            if ($totalProviders === 0) {
                $issues[] = 'No SSL providers configured';
                $score = 0;
            } else {
                $healthPercent = ($healthyProviders / $totalProviders) * 100;
                $score = (int) $healthPercent;

                if ($healthPercent < 50) {
                    $issues[] = "Only {$healthyProviders}/{$totalProviders} providers are healthy";
                } elseif ($healthPercent < 80) {
                    $warnings[] = "Provider availability is at {$healthPercent}%";
                }
            }

            $status = $score < 50 ? 'critical' : ($score < 80 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'score' => $score,
                'message' => "Provider health: {$healthyProviders}/{$totalProviders} providers operational",
                'issues' => $issues,
                'warnings' => $warnings,
                'details' => [
                    'total_providers' => $totalProviders,
                    'healthy_providers' => $healthyProviders,
                    'provider_results' => $healthResults
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'score' => 0,
                'message' => 'Provider health check failed',
                'issues' => ["Provider health check error: {$e->getMessage()}"]
            ];
        }
    }

    /**
     * 証明書の健全性チェック
     */
    private function checkCertificateHealth(): array
    {
        $issues = [];
        $warnings = [];
        $score = 100;

        try {
            $totalCerts = Certificate::count();
            $activeCerts = Certificate::where('status', Certificate::STATUS_ISSUED)->count();
            $expiringSoon = Certificate::where('status', Certificate::STATUS_ISSUED)
                                     ->where('expires_at', '<=', now()->addDays(7))
                                     ->count();
            $expired = Certificate::where('expires_at', '<=', now())->count();
            $failed = Certificate::where('status', Certificate::STATUS_FAILED)->count();

            if ($totalCerts === 0) {
                $warnings[] = 'No certificates in system';
                $score = 90;
            } else {
                $successRate = ($activeCerts / $totalCerts) * 100;
                $score = max(0, (int) $successRate);

                if ($expired > 0) {
                    $issues[] = "{$expired} certificates have expired";
                    $score -= ($expired / $totalCerts) * 30;
                }

                if ($expiringSoon > 0) {
                    $warnings[] = "{$expiringSoon} certificates expire within 7 days";
                    $score -= ($expiringSoon / $totalCerts) * 10;
                }

                if ($failed > 0) {
                    $failureRate = ($failed / $totalCerts) * 100;
                    if ($failureRate > 10) {
                        $issues[] = "High certificate failure rate: {$failureRate}%";
                        $score -= $failureRate;
                    } else {
                        $warnings[] = "{$failed} certificates have failed";
                        $score -= $failureRate / 2;
                    }
                }
            }

            $score = max(0, min(100, $score));
            $status = $score < 50 ? 'critical' : ($score < 80 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'score' => (int) $score,
                'message' => "Certificate health: {$activeCerts}/{$totalCerts} active certificates",
                'issues' => $issues,
                'warnings' => $warnings,
                'details' => [
                    'total_certificates' => $totalCerts,
                    'active_certificates' => $activeCerts,
                    'expiring_soon' => $expiringSoon,
                    'expired' => $expired,
                    'failed' => $failed
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'score' => 0,
                'message' => 'Certificate health check failed',
                'issues' => ["Certificate health check error: {$e->getMessage()}"]
            ];
        }
    }

    /**
     * サブスクリプションの健全性チェック
     */
    private function checkSubscriptionHealth(): array
    {
        $issues = [];
        $warnings = [];
        $score = 100;

        try {
            $totalSubs = Subscription::count();
            $activeSubs = Subscription::where('status', 'active')->count();
            $pastDue = Subscription::where('status', 'past_due')->count();
            $cancelled = Subscription::where('status', 'cancelled')->count();

            if ($totalSubs === 0) {
                $warnings[] = 'No subscriptions in system';
                $score = 90;
            } else {
                $activeRate = ($activeSubs / $totalSubs) * 100;
                $score = max(0, (int) $activeRate);

                if ($pastDue > 0) {
                    $pastDueRate = ($pastDue / $totalSubs) * 100;
                    if ($pastDueRate > 20) {
                        $issues[] = "High past due rate: {$pastDueRate}%";
                        $score -= $pastDueRate;
                    } else {
                        $warnings[] = "{$pastDue} subscriptions are past due";
                        $score -= $pastDueRate / 2;
                    }
                }

                $churnRate = ($cancelled / $totalSubs) * 100;
                if ($churnRate > 30) {
                    $warnings[] = "High churn rate: {$churnRate}%";
                }
            }

            $score = max(0, min(100, $score));
            $status = $score < 50 ? 'critical' : ($score < 80 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'score' => (int) $score,
                'message' => "Subscription health: {$activeSubs}/{$totalSubs} active subscriptions",
                'issues' => $issues,
                'warnings' => $warnings,
                'details' => [
                    'total_subscriptions' => $totalSubs,
                    'active_subscriptions' => $activeSubs,
                    'past_due' => $pastDue,
                    'cancelled' => $cancelled
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'score' => 0,
                'message' => 'Subscription health check failed',
                'issues' => ["Subscription health check error: {$e->getMessage()}"]
            ];
        }
    }

    /**
     * データベースの健全性チェック
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $startTime = microtime(true);
            
            // 基本的な接続テスト
            DB::connection()->getPdo();
            
            // パフォーマンステスト
            Certificate::count();
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            $issues = [];
            $warnings = [];
            $score = 100;

            if ($responseTime > 5000) { // 5秒以上
                $issues[] = "Database response time is very slow: {$responseTime}ms";
                $score = 30;
            } elseif ($responseTime > 1000) { // 1秒以上
                $warnings[] = "Database response time is slow: {$responseTime}ms";
                $score = 70;
            }

            $status = $score < 50 ? 'critical' : ($score < 80 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'score' => $score,
                'message' => "Database health: {$responseTime}ms response time",
                'issues' => $issues,
                'warnings' => $warnings,
                'details' => [
                    'response_time_ms' => $responseTime,
                    'connection_status' => 'connected'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'score' => 0,
                'message' => 'Database health check failed',
                'issues' => ["Database connection error: {$e->getMessage()}"]
            ];
        }
    }

    /**
     * キューの健全性チェック
     */
    private function checkQueueHealth(): array
    {
        try {
            $queueSize = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            
            $issues = [];
            $warnings = [];
            $score = 100;

            if ($queueSize > 1000) {
                $issues[] = "Queue backlog is very high: {$queueSize} jobs";
                $score = 40;
            } elseif ($queueSize > 500) {
                $warnings[] = "Queue backlog is high: {$queueSize} jobs";
                $score = 70;
            }

            if ($failedJobs > 50) {
                $issues[] = "High number of failed jobs in last 24h: {$failedJobs}";
                $score -= 30;
            } elseif ($failedJobs > 10) {
                $warnings[] = "Some jobs failed in last 24h: {$failedJobs}";
                $score -= 10;
            }

            $score = max(0, $score);
            $status = $score < 50 ? 'critical' : ($score < 80 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'score' => $score,
                'message' => "Queue health: {$queueSize} pending, {$failedJobs} failed",
                'issues' => $issues,
                'warnings' => $warnings,
                'details' => [
                    'pending_jobs' => $queueSize,
                    'failed_jobs_24h' => $failedJobs
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'score' => 0,
                'message' => 'Queue health check failed',
                'issues' => ["Queue health check error: {$e->getMessage()}"]
            ];
        }
    }

    /**
     * キャッシュの健全性チェック
     */
    private function checkCacheHealth(): array
    {
        try {
            $testKey = 'health_check_' . uniqid();
            $testValue = 'test_value';
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'score' => 100,
                    'message' => 'Cache is working properly',
                    'issues' => [],
                    'warnings' => []
                ];
            } else {
                return [
                    'status' => 'critical',
                    'score' => 0,
                    'message' => 'Cache read/write test failed',
                    'issues' => ['Cache is not working properly']
                ];
            }

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'score' => 0,
                'message' => 'Cache health check failed',
                'issues' => ["Cache error: {$e->getMessage()}"]
            ];
        }
    }

    /**
     * ストレージの健全性チェック
     */
    private function checkStorageHealth(): array
    {
        try {
            $logPath = storage_path('logs');
            $appPath = storage_path('app');
            
            $issues = [];
            $warnings = [];
            $score = 100;

            if (!is_writable($logPath)) {
                $issues[] = 'Logs directory is not writable';
                $score -= 50;
            }

            if (!is_writable($appPath)) {
                $issues[] = 'App storage directory is not writable';
                $score -= 50;
            }

            // ディスク容量チェック
            $freeBytes = disk_free_space(storage_path());
            $totalBytes = disk_total_space(storage_path());
            
            if ($freeBytes && $totalBytes) {
                $freePercent = ($freeBytes / $totalBytes) * 100;
                
                if ($freePercent < 5) {
                    $issues[] = "Very low disk space: {$freePercent}% free";
                    $score -= 40;
                } elseif ($freePercent < 15) {
                    $warnings[] = "Low disk space: {$freePercent}% free";
                    $score -= 20;
                }
            }

            $score = max(0, $score);
            $status = $score < 50 ? 'critical' : ($score < 80 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'score' => $score,
                'message' => 'Storage health check completed',
                'issues' => $issues,
                'warnings' => $warnings,
                'details' => [
                    'logs_writable' => is_writable($logPath),
                    'app_writable' => is_writable($appPath),
                    'free_space_percent' => isset($freePercent) ? round($freePercent, 2) : null
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'score' => 0,
                'message' => 'Storage health check failed',
                'issues' => ["Storage error: {$e->getMessage()}"]
            ];
        }
    }

    /**
     * 全体ステータスを決定
     */
    private function getOverallStatus(int $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'poor';
        return 'critical';
    }

    /**
     * ヘルス結果をキャッシュ
     */
    private function cacheHealthResults(array $healthSummary): void
    {
        Cache::put('ssl_system_health', $healthSummary, now()->addMinutes(30));
        
        // 履歴として保存
        $historyKey = 'ssl_health_history_' . now()->format('Y_m_d_H');
        Cache::put($historyKey, $healthSummary, now()->addDays(7));
    }

    /**
     * 通知処理
     */
    private function processNotifications(array $healthSummary): void
    {
        $overallScore = $healthSummary['overall_score'];
        $criticalIssues = $healthSummary['critical_issues'];

        // 重大な問題がある場合は管理者に通知
        if ($overallScore < $this->healthThreshold || !empty($criticalIssues)) {
            $this->sendHealthIssueNotification($healthSummary);
        }

        // 期限切れ間近の証明書の通知
        if (in_array('certificates', $this->checkTypes)) {
            $this->sendExpiryNotifications();
        }
    }

    /**
     * システム健全性問題通知を送信
     */
    private function sendHealthIssueNotification(array $healthSummary): void
    {
        try {
            $adminUsers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['super_admin', 'admin', 'ssl_manager']);
            })->get();

            foreach ($adminUsers as $admin) {
                $admin->notify(new SystemHealthIssueNotification($healthSummary));
            }

            Log::info('System health issue notifications sent', [
                'admin_count' => $adminUsers->count(),
                'overall_score' => $healthSummary['overall_score'],
                'critical_issues_count' => count($healthSummary['critical_issues'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send health issue notifications', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 期限切れ通知を送信
     */
    private function sendExpiryNotifications(): void
    {
        $expiringCertificates = Certificate::where('status', Certificate::STATUS_ISSUED)
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->where('expires_at', '>', now())
            ->with(['subscription.user'])
            ->get();

        if ($expiringCertificates->isNotEmpty()) {
            $userCertificates = $expiringCertificates->groupBy(function ($cert) {
                return $cert->subscription->user_id;
            });

            foreach ($userCertificates as $userId => $certificates) {
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new CertificateExpiringNotification(
                        $certificates->toArray(),
                        'urgent',
                        7
                    ));
                }
            }
        }
    }

    /**
     * 自動修復処理
     */
    private function performAutoRemediation(array $healthResults): void
    {
        foreach ($healthResults as $checkType => $result) {
            if ($result['status'] === 'critical') {
                $this->attemptAutoRemediation($checkType, $result);
            }
        }
    }

    /**
     * 自動修復を試行
     */
    private function attemptAutoRemediation(string $checkType, array $result): void
    {
        switch ($checkType) {
            case 'cache':
                try {
                    Cache::flush();
                    Log::info('Cache cleared for auto-remediation');
                } catch (\Exception $e) {
                    Log::error('Auto-remediation failed for cache', ['error' => $e->getMessage()]);
                }
                break;

            case 'queues':
                // 失敗したジョブを再試行
                try {
                    $recentFailedJobs = DB::table('failed_jobs')
                        ->where('failed_at', '>=', now()->subHour())
                        ->limit(10)
                        ->get();

                    foreach ($recentFailedJobs as $failedJob) {
                        // 実際の再試行ロジックはより複雑になります
                        Log::info('Auto-remediation: Found failed job for potential retry', [
                            'job_id' => $failedJob->id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Auto-remediation failed for queues', ['error' => $e->getMessage()]);
                }
                break;

            default:
                Log::info("No auto-remediation available for {$checkType}");
        }
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Monitor SSL system health job failed completely', [
            'check_types' => $this->checkTypes,
            'send_notifications' => $this->sendNotifications,
            'health_threshold' => $this->healthThreshold,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}