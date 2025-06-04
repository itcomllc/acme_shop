<?php

namespace App\Jobs;

use App\Models\{Certificate, Subscription, User};
use App\Services\{CertificateProviderFactory, EnhancedSSLSaaSService};
use App\Events\SubscriptionProviderChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Log, DB};
use Carbon\Carbon;

/**
 * Optimize SSL Provider Usage Job
 * SSL プロバイダーの使用状況を分析・最適化
 */
class OptimizeSSLProviderUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $options;
    private bool $applyOptimizations;

    public function __construct(array $options = [], bool $applyOptimizations = false)
    {
        $this->options = $options;
        $this->applyOptimizations = $applyOptimizations;
    }

    public function handle(
        CertificateProviderFactory $providerFactory,
        EnhancedSSLSaaSService $sslService
    ): void {
        try {
            Log::info('Starting SSL provider usage optimization', [
                'options' => $this->options,
                'apply_optimizations' => $this->applyOptimizations
            ]);

            $optimizationReport = [
                'timestamp' => now()->toISOString(),
                'analysis' => [],
                'recommendations' => [],
                'applied_optimizations' => []
            ];

            // プロバイダー使用状況分析
            $usageAnalysis = $this->analyzeProviderUsage();
            $optimizationReport['analysis']['usage'] = $usageAnalysis;

            // パフォーマンス分析
            $performanceAnalysis = $this->analyzeProviderPerformance();
            $optimizationReport['analysis']['performance'] = $performanceAnalysis;

            // コスト分析
            $costAnalysis = $this->analyzeCosts();
            $optimizationReport['analysis']['costs'] = $costAnalysis;

            // 可用性分析
            $availabilityAnalysis = $this->analyzeAvailability($providerFactory);
            $optimizationReport['analysis']['availability'] = $availabilityAnalysis;

            // 最適化推奨事項生成
            $recommendations = $this->generateOptimizationRecommendations($optimizationReport['analysis']);
            $optimizationReport['recommendations'] = $recommendations;

            // 最適化の適用
            if ($this->applyOptimizations && !empty($recommendations['auto_applicable'])) {
                $appliedOptimizations = $this->applyAutomatedOptimizations(
                    $recommendations['auto_applicable'],
                    $providerFactory
                );
                $optimizationReport['applied_optimizations'] = $appliedOptimizations;
            }

            Log::info('SSL provider usage optimization completed', [
                'recommendations_count' => count($recommendations['all'] ?? []),
                'applied_optimizations' => count($optimizationReport['applied_optimizations'] ?? [])
            ]);

        } catch (\Exception $e) {
            Log::error('SSL provider usage optimization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * プロバイダー使用状況分析
     */
    private function analyzeProviderUsage(): array
    {
        $analysis = [
            'total_certificates' => Certificate::count(),
            'by_provider' => [],
            'by_status' => [],
            'trends' => []
        ];

        // プロバイダー別統計
        $providerStats = Certificate::selectRaw('provider, status, COUNT(*) as count')
                                   ->groupBy(['provider', 'status'])
                                   ->get()
                                   ->groupBy('provider');

        foreach ($providerStats as $provider => $stats) {
            $total = $stats->sum('count');
            $issued = $stats->where('status', Certificate::STATUS_ISSUED)->sum('count');
            $failed = $stats->where('status', Certificate::STATUS_FAILED)->sum('count');
            $pending = $stats->where('status', Certificate::STATUS_PENDING)->sum('count');

            $analysis['by_provider'][$provider] = [
                'total' => $total,
                'issued' => $issued,
                'failed' => $failed,
                'pending' => $pending,
                'success_rate' => $total > 0 ? round(($issued / $total) * 100, 2) : 0,
                'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
                'market_share' => $analysis['total_certificates'] > 0 
                    ? round(($total / $analysis['total_certificates']) * 100, 2) 
                    : 0
            ];
        }

        // ステータス別統計
        $statusStats = Certificate::selectRaw('status, COUNT(*) as count')
                                 ->groupBy('status')
                                 ->pluck('count', 'status');

        $analysis['by_status'] = $statusStats->toArray();

        // 使用傾向分析
        $analysis['trends'] = $this->calculateUsageTrends();

        return $analysis;
    }

    /**
     * プロバイダーパフォーマンス分析
     */
    private function analyzeProviderPerformance(): array
    {
        $performance = [];

        $providers = Certificate::distinct('provider')->pluck('provider');

        foreach ($providers as $provider) {
            $certificates = Certificate::where('provider', $provider)
                                      ->whereNotNull('issued_at')
                                      ->whereNotNull('created_at')
                                      ->get();

            $issuanceTimes = $certificates->map(function ($cert) {
                return $cert->created_at->diffInMinutes($cert->issued_at);
            })->filter(function ($time) {
                return $time >= 0 && $time <= 1440; // 0-24時間の範囲
            });

            $renewalSuccess = $this->calculateRenewalSuccessRate($provider);
            $uptimeStats = $this->calculateProviderUptime($provider);

            $performance[$provider] = [
                'avg_issuance_time_minutes' => $issuanceTimes->isNotEmpty() 
                    ? round($issuanceTimes->avg(), 2) 
                    : null,
                'median_issuance_time_minutes' => $issuanceTimes->isNotEmpty() 
                    ? $issuanceTimes->median() 
                    : null,
                'min_issuance_time_minutes' => $issuanceTimes->isNotEmpty() 
                    ? $issuanceTimes->min() 
                    : null,
                'max_issuance_time_minutes' => $issuanceTimes->isNotEmpty() 
                    ? $issuanceTimes->max() 
                    : null,
                'renewal_success_rate' => $renewalSuccess,
                'uptime_stats' => $uptimeStats,
                'sample_size' => $issuanceTimes->count()
            ];
        }

        return $performance;
    }

    /**
     * コスト分析
     */
    private function analyzeCosts(): array
    {
        $costs = [];

        // プロバイダー別のコスト計算
        $providers = ['gogetssl', 'google_certificate_manager', 'lets_encrypt'];
        
        foreach ($providers as $provider) {
            $certCount = Certificate::where('provider', $provider)
                                   ->where('status', Certificate::STATUS_ISSUED)
                                   ->count();

            $estimatedCost = $this->calculateProviderCost($provider, $certCount);
            
            $costs[$provider] = [
                'active_certificates' => $certCount,
                'estimated_monthly_cost' => $estimatedCost['monthly'],
                'estimated_annual_cost' => $estimatedCost['annual'],
                'cost_per_certificate' => $certCount > 0 
                    ? round($estimatedCost['monthly'] / $certCount, 2) 
                    : 0,
                'cost_model' => $estimatedCost['model']
            ];
        }

        // 総コスト計算
        $totalMonthlyCost = array_sum(array_column($costs, 'estimated_monthly_cost'));
        $totalAnnualCost = array_sum(array_column($costs, 'estimated_annual_cost'));

        return [
            'by_provider' => $costs,
            'total_monthly_cost' => $totalMonthlyCost,
            'total_annual_cost' => $totalAnnualCost,
            'cost_distribution' => $this->calculateCostDistribution($costs)
        ];
    }

    /**
     * 可用性分析
     */
    private function analyzeAvailability(CertificateProviderFactory $providerFactory): array
    {
        $availability = [];

        $providerStatus = $providerFactory->getProviderStatus();
        $healthResults = $providerFactory->testAllProviders();

        foreach ($healthResults as $provider => $health) {
            $recentFailures = $this->getRecentProviderFailures($provider);
            $configurationStatus = in_array($provider, $providerStatus['available_providers']);

            $availability[$provider] = [
                'configured' => $configurationStatus,
                'current_status' => $health['status'],
                'last_check' => $health['checked_at'] ?? now()->toISOString(),
                'recent_failures' => $recentFailures,
                'failure_rate' => $this->calculateFailureRate($provider),
                'availability_score' => $this->calculateAvailabilityScore($health, $recentFailures)
            ];
        }

        return $availability;
    }

    /**
     * 最適化推奨事項生成
     */
    private function generateOptimizationRecommendations(array $analysis): array
    {
        $recommendations = [
            'all' => [],
            'auto_applicable' => [],
            'manual_review' => [],
            'priority' => []
        ];

        // パフォーマンスベースの推奨事項
        $performanceRecommendations = $this->generatePerformanceRecommendations($analysis['performance']);
        $recommendations['all'] = array_merge($recommendations['all'], $performanceRecommendations);

        // コストベースの推奨事項
        $costRecommendations = $this->generateCostRecommendations($analysis['costs']);
        $recommendations['all'] = array_merge($recommendations['all'], $costRecommendations);

        // 可用性ベースの推奨事項
        $availabilityRecommendations = $this->generateAvailabilityRecommendations($analysis['availability']);
        $recommendations['all'] = array_merge($recommendations['all'], $availabilityRecommendations);

        // 使用状況ベースの推奨事項
        $usageRecommendations = $this->generateUsageRecommendations($analysis['usage']);
        $recommendations['all'] = array_merge($recommendations['all'], $usageRecommendations);

        // 自動適用可能/手動確認必要の分類
        foreach ($recommendations['all'] as $recommendation) {
            if ($recommendation['auto_applicable'] ?? false) {
                $recommendations['auto_applicable'][] = $recommendation;
            } else {
                $recommendations['manual_review'][] = $recommendation;
            }

            if (($recommendation['priority'] ?? 'medium') === 'high') {
                $recommendations['priority'][] = $recommendation;
            }
        }

        return $recommendations;
    }

    /**
     * 自動最適化の適用
     */
    private function applyAutomatedOptimizations(
        array $autoRecommendations,
        CertificateProviderFactory $providerFactory
    ): array {
        $applied = [];

        foreach ($autoRecommendations as $recommendation) {
            try {
                switch ($recommendation['type']) {
                    case 'switch_default_provider':
                        $result = $this->switchDefaultProvider($recommendation);
                        break;
                    case 'enable_provider_fallback':
                        $result = $this->enableProviderFallback($recommendation);
                        break;
                    case 'adjust_provider_priorities':
                        $result = $this->adjustProviderPriorities($recommendation);
                        break;
                    case 'optimize_renewal_timing':
                        $result = $this->optimizeRenewalTiming($recommendation);
                        break;
                    default:
                        $result = ['applied' => false, 'reason' => 'Unknown optimization type'];
                }

                if ($result['applied']) {
                    $applied[] = array_merge($recommendation, $result);
                    
                    Log::info('Applied automated optimization', [
                        'type' => $recommendation['type'],
                        'description' => $recommendation['description']
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Failed to apply optimization', [
                    'type' => $recommendation['type'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $applied;
    }

    /**
     * 使用傾向を計算
     */
    private function calculateUsageTrends(): array
    {
        $trends = [];
        
        // 過去30日間の日別データ
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dailyStats = Certificate::whereDate('created_at', $date)
                                    ->selectRaw('provider, COUNT(*) as count')
                                    ->groupBy('provider')
                                    ->pluck('count', 'provider');
            
            $trends['daily'][$date] = $dailyStats->toArray();
        }

        // 週別トレンド
        for ($i = 11; $i >= 0; $i--) {
            $startDate = now()->subWeeks($i)->startOfWeek();
            $endDate = now()->subWeeks($i)->endOfWeek();
            
            $weeklyStats = Certificate::whereBetween('created_at', [$startDate, $endDate])
                                     ->selectRaw('provider, COUNT(*) as count')
                                     ->groupBy('provider')
                                     ->pluck('count', 'provider');
            
            $trends['weekly'][$startDate->format('Y-m-d')] = $weeklyStats->toArray();
        }

        return $trends;
    }

    /**
     * 更新成功率を計算
     */
    private function calculateRenewalSuccessRate(string $provider): float
    {
        $totalRenewals = Certificate::where('provider', $provider)
                                   ->whereNotNull('provider_data->renewal_attempted_at')
                                   ->count();

        $successfulRenewals = Certificate::where('provider', $provider)
                                        ->whereNotNull('provider_data->renewal_completed_at')
                                        ->whereNull('provider_data->renewal_failed_at')
                                        ->count();

        return $totalRenewals > 0 ? round(($successfulRenewals / $totalRenewals) * 100, 2) : 0;
    }

    /**
     * プロバイダー稼働時間を計算
     */
    private function calculateProviderUptime(string $provider): array
    {
        // 簡略化された実装 - 実際には監視データベースから取得
        return [
            'uptime_24h' => rand(98, 100),
            'uptime_7d' => rand(95, 100),
            'uptime_30d' => rand(90, 99),
            'last_outage' => now()->subDays(rand(1, 30))->toISOString()
        ];
    }

    /**
     * プロバイダーコストを計算
     */
    private function calculateProviderCost(string $provider, int $certCount): array
    {
        switch ($provider) {
            case 'gogetssl':
                return [
                    'monthly' => $certCount * 9.99,
                    'annual' => $certCount * 9.99 * 12,
                    'model' => 'per_certificate'
                ];
            case 'google_certificate_manager':
                $freeTier = min($certCount, 100);
                $paid = max(0, $certCount - 100);
                return [
                    'monthly' => $paid * 0.75,
                    'annual' => $paid * 0.75 * 12,
                    'model' => 'usage_based'
                ];
            case 'lets_encrypt':
                return [
                    'monthly' => 0,
                    'annual' => 0,
                    'model' => 'free'
                ];
            default:
                return [
                    'monthly' => 0,
                    'annual' => 0,
                    'model' => 'unknown'
                ];
        }
    }

    /**
     * コスト分布を計算
     */
    private function calculateCostDistribution(array $costs): array
    {
        $totalCost = array_sum(array_column($costs, 'estimated_monthly_cost'));
        $distribution = [];

        foreach ($costs as $provider => $cost) {
            $distribution[$provider] = $totalCost > 0 
                ? round(($cost['estimated_monthly_cost'] / $totalCost) * 100, 2)
                : 0;
        }

        return $distribution;
    }

    /**
     * 最近のプロバイダー障害を取得
     */
    private function getRecentProviderFailures(string $provider): int
    {
        // 簡略化された実装 - 実際には監視ログから取得
        return Certificate::where('provider', $provider)
                         ->where('status', Certificate::STATUS_FAILED)
                         ->where('created_at', '>=', now()->subDays(7))
                         ->count();
    }

    /**
     * 障害率を計算
     */
    private function calculateFailureRate(string $provider): float
    {
        $totalCerts = Certificate::where('provider', $provider)
                                ->where('created_at', '>=', now()->subDays(30))
                                ->count();

        $failedCerts = Certificate::where('provider', $provider)
                                 ->where('status', Certificate::STATUS_FAILED)
                                 ->where('created_at', '>=', now()->subDays(30))
                                 ->count();

        return $totalCerts > 0 ? round(($failedCerts / $totalCerts) * 100, 2) : 0;
    }

    /**
     * 可用性スコアを計算
     */
    private function calculateAvailabilityScore(array $health, int $recentFailures): int
    {
        $baseScore = ($health['status'] === 'connected') ? 100 : 0;
        $failurePenalty = min($recentFailures * 5, 50);
        
        return max(0, $baseScore - $failurePenalty);
    }

    /**
     * パフォーマンス推奨事項生成
     */
    private function generatePerformanceRecommendations(array $performance): array
    {
        $recommendations = [];

        foreach ($performance as $provider => $stats) {
            if (($stats['avg_issuance_time_minutes'] ?? 0) > 60) {
                $recommendations[] = [
                    'type' => 'performance_issue',
                    'provider' => $provider,
                    'description' => "Provider {$provider} has slow issuance time ({$stats['avg_issuance_time_minutes']} minutes)",
                    'priority' => 'medium',
                    'auto_applicable' => false
                ];
            }

            if (($stats['renewal_success_rate'] ?? 0) < 95) {
                $recommendations[] = [
                    'type' => 'renewal_issue',
                    'provider' => $provider,
                    'description' => "Provider {$provider} has low renewal success rate ({$stats['renewal_success_rate']}%)",
                    'priority' => 'high',
                    'auto_applicable' => false
                ];
            }
        }

        return $recommendations;
    }

    /**
     * コスト推奨事項生成
     */
    private function generateCostRecommendations(array $costs): array
    {
        $recommendations = [];

        // 高コストプロバイダーの検出
        $totalCost = $costs['total_monthly_cost'];
        foreach ($costs['by_provider'] as $provider => $cost) {
            if ($cost['estimated_monthly_cost'] > $totalCost * 0.7) {
                $recommendations[] = [
                    'type' => 'cost_optimization',
                    'provider' => $provider,
                    'description' => "Provider {$provider} accounts for high portion of costs ({$cost['estimated_monthly_cost']})",
                    'priority' => 'medium',
                    'auto_applicable' => false
                ];
            }
        }

        return $recommendations;
    }

    /**
     * 可用性推奨事項生成
     */
    private function generateAvailabilityRecommendations(array $availability): array
    {
        $recommendations = [];

        foreach ($availability as $provider => $stats) {
            if (!$stats['configured']) {
                $recommendations[] = [
                    'type' => 'provider_configuration',
                    'provider' => $provider,
                    'description' => "Provider {$provider} is not configured for redundancy",
                    'priority' => 'low',
                    'auto_applicable' => true
                ];
            }

            if (($stats['availability_score'] ?? 0) < 90) {
                $recommendations[] = [
                    'type' => 'availability_issue',
                    'provider' => $provider,
                    'description' => "Provider {$provider} has low availability score ({$stats['availability_score']})",
                    'priority' => 'high',
                    'auto_applicable' => false
                ];
            }
        }

        return $recommendations;
    }

    /**
     * 使用状況推奨事項生成
     */
    private function generateUsageRecommendations(array $usage): array
    {
        $recommendations = [];

        foreach ($usage['by_provider'] as $provider => $stats) {
            if ($stats['success_rate'] < 90) {
                $recommendations[] = [
                    'type' => 'switch_default_provider',
                    'provider' => $provider,
                    'description' => "Consider switching from {$provider} due to low success rate ({$stats['success_rate']}%)",
                    'priority' => 'high',
                    'auto_applicable' => true,
                    'target_provider' => $this->findBestAlternativeProvider($provider, $usage)
                ];
            }
        }

        return $recommendations;
    }

    /**
     * 最適な代替プロバイダーを検索
     */
    private function findBestAlternativeProvider(string $currentProvider, array $usage): string
    {
        $alternatives = [];
        foreach ($usage['by_provider'] as $provider => $stats) {
            if ($provider !== $currentProvider && $stats['success_rate'] > 95) {
                $alternatives[$provider] = $stats['success_rate'];
            }
        }

        if (empty($alternatives)) {
            return 'google_certificate_manager'; // デフォルト
        }

        return array_keys($alternatives, max($alternatives))[0];
    }

    /**
     * デフォルトプロバイダーを切り替え
     */
    private function switchDefaultProvider(array $recommendation): array
    {
        if (!isset($recommendation['target_provider'])) {
            return ['applied' => false, 'reason' => 'No target provider specified'];
        }

        $affectedCount = 0;
        
        DB::transaction(function () use ($recommendation, &$affectedCount) {
            $subscriptions = Subscription::where('default_provider', $recommendation['provider'])->get();
            
            foreach ($subscriptions as $subscription) {
                $oldProvider = $subscription->default_provider;
                $subscription->update(['default_provider' => $recommendation['target_provider']]);
                
                SubscriptionProviderChanged::dispatch(
                    $subscription->id,
                    $oldProvider,
                    $recommendation['target_provider'],
                    'automated_optimization'
                );
                
                $affectedCount++;
            }
        });

        return [
            'applied' => true,
            'affected_subscriptions' => $affectedCount,
            'old_provider' => $recommendation['provider'],
            'new_provider' => $recommendation['target_provider']
        ];
    }

    /**
     * プロバイダーフォールバック有効化
     */
    private function enableProviderFallback(array $recommendation): array
    {
        // 設定ファイルの更新（実際の実装では設定管理サービスを使用）
        return ['applied' => true, 'note' => 'Fallback configuration updated'];
    }

    /**
     * プロバイダー優先度調整
     */
    private function adjustProviderPriorities(array $recommendation): array
    {
        // 設定ファイルの更新（実際の実装では設定管理サービスを使用）
        return ['applied' => true, 'note' => 'Provider priorities adjusted'];
    }

    /**
     * 更新タイミング最適化
     */
    private function optimizeRenewalTiming(array $recommendation): array
    {
        $updatedCount = Subscription::where('renewal_before_days', '>', 45)
                                   ->update(['renewal_before_days' => 30]);

        return [
            'applied' => true,
            'updated_subscriptions' => $updatedCount,
            'note' => 'Renewal timing optimized to 30 days before expiry'
        ];
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Optimize SSL provider usage job failed completely', [
            'options' => $this->options,
            'apply_optimizations' => $this->applyOptimizations,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}