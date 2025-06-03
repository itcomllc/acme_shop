<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Certificate, Subscription};
use App\Services\{EnhancedSSLSaaSService, CertificateProviderFactory};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Log, Cache, DB, Auth};

/**
 * SSL Health API Controller
 * 
 * Handles SSL system health monitoring via API endpoints
 */
class SSLHealthController extends Controller
{
    public function __construct(
        private readonly EnhancedSSLSaaSService $sslService,
        private readonly CertificateProviderFactory $providerFactory
    ) {}

    /**
     * Get overall SSL system health status
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $includeDetails = $request->boolean('include_details', false);
            
            // Get cached health data or generate new
            $cacheKey = 'ssl_system_health_' . ($includeDetails ? 'detailed' : 'summary');
            $healthData = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($includeDetails) {
                return $this->generateHealthData($includeDetails);
            });

            return response()->json([
                'success' => true,
                'data' => $healthData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get SSL system health', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get certificate health status
     */
    public function certificates(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get certificate statistics
            $query = Certificate::query();
            
            // Filter by user if not admin
            if (!$request->user()->can('admin.ssl.view_all')) {
                $query->whereHas('subscription', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            $totalCertificates = $query->count();
            $activeCertificates = $query->where('status', Certificate::STATUS_ISSUED)->count();
            $pendingCertificates = $query->where('status', Certificate::STATUS_PENDING)->count();
            $failedCertificates = $query->where('status', Certificate::STATUS_FAILED)->count();
            $revokedCertificates = $query->where('status', Certificate::STATUS_REVOKED)->count();
            
            // Expiring certificates (next 30 days)
            $expiringCertificates = $query->where('status', Certificate::STATUS_ISSUED)
                                         ->where('expires_at', '<=', now()->addDays(30))
                                         ->where('expires_at', '>', now())
                                         ->count();

            // Expired certificates
            $expiredCertificates = $query->where('expires_at', '<=', now())->count();

            // Provider distribution
            $providerStats = $query->select('provider', DB::raw('count(*) as count'))
                                  ->groupBy('provider')
                                  ->pluck('count', 'provider')
                                  ->toArray();

            // Certificate age distribution
            $ageDistribution = [
                'new' => $query->where('created_at', '>=', now()->subDays(7))->count(),
                'recent' => $query->whereBetween('created_at', [now()->subDays(30), now()->subDays(7)])->count(),
                'old' => $query->where('created_at', '<', now()->subDays(30))->count(),
            ];

            // Health score calculation
            $healthScore = $this->calculateCertificateHealthScore([
                'total' => $totalCertificates,
                'active' => $activeCertificates,
                'failed' => $failedCertificates,
                'expiring' => $expiringCertificates,
                'expired' => $expiredCertificates,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_certificates' => $totalCertificates,
                        'active_certificates' => $activeCertificates,
                        'pending_certificates' => $pendingCertificates,
                        'failed_certificates' => $failedCertificates,
                        'revoked_certificates' => $revokedCertificates,
                        'expiring_certificates' => $expiringCertificates,
                        'expired_certificates' => $expiredCertificates,
                    ],
                    'provider_distribution' => $providerStats,
                    'age_distribution' => $ageDistribution,
                    'health_metrics' => [
                        'health_score' => $healthScore,
                        'success_rate' => $totalCertificates > 0 ? round(($activeCertificates / $totalCertificates) * 100, 2) : 0,
                        'failure_rate' => $totalCertificates > 0 ? round(($failedCertificates / $totalCertificates) * 100, 2) : 0,
                        'expiry_risk' => $activeCertificates > 0 ? round(($expiringCertificates / $activeCertificates) * 100, 2) : 0,
                    ],
                    'status' => $this->getCertificateHealthStatus($healthScore),
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get certificate health', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get certificate health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get provider health status
     */
    public function providers(Request $request): JsonResponse
    {
        try {
            $forceRefresh = $request->boolean('force_refresh', false);
            $cacheKey = 'ssl_provider_health_detailed';
            
            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            $providerHealth = Cache::remember($cacheKey, now()->addMinutes(10), function () {
                return $this->generateProviderHealthData();
            });

            return response()->json([
                'success' => true,
                'data' => $providerHealth
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get provider health', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription health status
     */
    public function subscriptions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get subscription statistics
            $query = Subscription::query();
            
            // Filter by user if not admin
            if (!$request->user()->can('admin.ssl.view_all')) {
                $query->where('user_id', $user->id);
            }

            $totalSubscriptions = $query->count();
            $activeSubscriptions = $query->where('status', Subscription::STATUS_ACTIVE)->count();
            $pastDueSubscriptions = $query->where('status', Subscription::STATUS_PAST_DUE)->count();
            $cancelledSubscriptions = $query->where('status', Subscription::STATUS_CANCELLED)->count();
            $pausedSubscriptions = $query->where('status', Subscription::STATUS_PAUSED)->count();

            // Plan distribution
            $planDistribution = $query->select('plan_type', DB::raw('count(*) as count'))
                                    ->groupBy('plan_type')
                                    ->pluck('count', 'plan_type')
                                    ->toArray();

            // Provider preference distribution
            $providerDistribution = $query->select('default_provider', DB::raw('count(*) as count'))
                                         ->groupBy('default_provider')
                                         ->pluck('count', 'default_provider')
                                         ->toArray();

            // Revenue metrics (for active subscriptions)
            $revenueMetrics = [
                'monthly_revenue' => $query->where('status', Subscription::STATUS_ACTIVE)
                                          ->where('billing_period', 'MONTHLY')
                                          ->sum('price'),
                'annual_revenue_projection' => $query->where('status', Subscription::STATUS_ACTIVE)
                                                   ->sum(DB::raw('CASE 
                                                       WHEN billing_period = "MONTHLY" THEN price * 12
                                                       WHEN billing_period = "QUARTERLY" THEN price * 4
                                                       WHEN billing_period = "ANNUALLY" THEN price
                                                       ELSE price * 12
                                                   END')),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_subscriptions' => $totalSubscriptions,
                        'active_subscriptions' => $activeSubscriptions,
                        'past_due_subscriptions' => $pastDueSubscriptions,
                        'cancelled_subscriptions' => $cancelledSubscriptions,
                        'paused_subscriptions' => $pausedSubscriptions,
                    ],
                    'distribution' => [
                        'by_plan' => $planDistribution,
                        'by_provider' => $providerDistribution,
                    ],
                    'revenue_metrics' => $revenueMetrics,
                    'health_metrics' => [
                        'active_rate' => $totalSubscriptions > 0 ? round(($activeSubscriptions / $totalSubscriptions) * 100, 2) : 0,
                        'churn_rate' => $totalSubscriptions > 0 ? round(($cancelledSubscriptions / $totalSubscriptions) * 100, 2) : 0,
                        'payment_issues' => $totalSubscriptions > 0 ? round(($pastDueSubscriptions / $totalSubscriptions) * 100, 2) : 0,
                    ],
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get subscription health', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get subscription health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get public health status (no auth required)
     */
    public function publicHealth(): JsonResponse
    {
        try {
            // Get minimal health info for public status page
            $cacheKey = 'ssl_public_health_status';
            
            $healthStatus = Cache::remember($cacheKey, now()->addMinutes(5), function () {
                $providerHealth = $this->sslService->performHealthCheck();
                
                $healthyProviders = 0;
                $totalProviders = count($providerHealth);
                
                foreach ($providerHealth as $result) {
                    if (($result['status'] ?? '') === 'connected') {
                        $healthyProviders++;
                    }
                }
                
                $overallStatus = $healthyProviders === $totalProviders ? 'operational' : 
                               ($healthyProviders > 0 ? 'degraded' : 'down');
                
                return [
                    'status' => $overallStatus,
                    'providers_operational' => $healthyProviders,
                    'total_providers' => $totalProviders,
                    'last_updated' => now()->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $healthStatus
            ]);

        } catch (\Exception $e) {
            // Don't expose internal errors in public endpoint
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'unknown',
                    'providers_operational' => 0,
                    'total_providers' => 0,
                    'last_updated' => now()->toISOString(),
                    'message' => 'Health check temporarily unavailable'
                ]
            ]);
        }
    }

    /**
     * Run comprehensive diagnostics
     */
    public function runDiagnostics(Request $request): JsonResponse
    {
        try {
            // Only allow admin users to run diagnostics
            if (!$request->user()->can('admin.ssl.run_diagnostics')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            $diagnostics = [
                'database_connectivity' => $this->testDatabaseConnectivity(),
                'cache_functionality' => $this->testCacheFunctionality(),
                'provider_configurations' => $this->testProviderConfigurations(),
                'certificate_processing' => $this->testCertificateProcessing(),
                'queue_system' => $this->testQueueSystem(),
                'file_permissions' => $this->testFilePermissions(),
            ];

            $overallStatus = 'healthy';
            $issues = [];

            foreach ($diagnostics as $test => $result) {
                if (!$result['status']) {
                    $overallStatus = 'unhealthy';
                    $issues[] = $test;
                }
            }

            Log::info('SSL diagnostics completed', [
                'status' => $overallStatus,
                'issues' => $issues,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_status' => $overallStatus,
                    'issues_found' => count($issues),
                    'failed_tests' => $issues,
                    'diagnostics' => $diagnostics,
                    'run_at' => now()->toISOString(),
                    'run_by' => Auth::user()->email,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('SSL diagnostics failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Diagnostics failed to run',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate comprehensive health data
     */
    private function generateHealthData(bool $includeDetails): array
    {
        $providerHealth = $this->sslService->performHealthCheck();
        
        $healthyProviders = 0;
        foreach ($providerHealth as $result) {
            if (($result['status'] ?? '') === 'connected') {
                $healthyProviders++;
            }
        }

        $certificateStats = [
            'total' => Certificate::count(),
            'active' => Certificate::where('status', Certificate::STATUS_ISSUED)->count(),
            'expiring_soon' => Certificate::where('status', Certificate::STATUS_ISSUED)
                                        ->where('expires_at', '<=', now()->addDays(30))
                                        ->count(),
            'failed' => Certificate::where('status', Certificate::STATUS_FAILED)->count(),
        ];

        $subscriptionStats = [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', Subscription::STATUS_ACTIVE)->count(),
            'past_due' => Subscription::where('status', Subscription::STATUS_PAST_DUE)->count(),
        ];

        $overallScore = $this->calculateOverallHealthScore($providerHealth, $certificateStats, $subscriptionStats);

        $healthData = [
            'overall_status' => $this->getOverallHealthStatus($overallScore),
            'health_score' => $overallScore,
            'provider_health' => [
                'healthy_providers' => $healthyProviders,
                'total_providers' => count($providerHealth),
                'status' => $healthyProviders === count($providerHealth) ? 'healthy' : 'degraded',
            ],
            'certificate_health' => $certificateStats,
            'subscription_health' => $subscriptionStats,
            'generated_at' => now()->toISOString(),
        ];

        if ($includeDetails) {
            $healthData['detailed_provider_health'] = $providerHealth;
            $healthData['system_metrics'] = [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ];
        }

        return $healthData;
    }

    /**
     * Generate provider health data
     */
    private function generateProviderHealthData(): array
    {
        $healthResults = $this->sslService->performHealthCheck();
        $providerStatus = $this->providerFactory->getProviderStatus();
        
        $detailedHealth = [];
        foreach ($healthResults as $provider => $result) {
            $detailedHealth[$provider] = array_merge($result, [
                'is_configured' => in_array($provider, $providerStatus['available_providers']),
                'configuration_errors' => $this->providerFactory->validateProviderConfig($provider),
                'last_checked' => now()->toISOString(),
            ]);
        }

        return [
            'summary' => $providerStatus,
            'detailed_health' => $detailedHealth,
            'overall_status' => $this->calculateProviderOverallStatus($healthResults),
        ];
    }

    /**
     * Calculate certificate health score
     */
    private function calculateCertificateHealthScore(array $stats): int
    {
        if ($stats['total'] === 0) return 100;

        $score = 100;
        
        // Penalty for failed certificates
        $failureRate = ($stats['failed'] / $stats['total']) * 100;
        $score -= $failureRate * 2; // 2 points per 1% failure rate

        // Penalty for expiring certificates
        if ($stats['active'] > 0) {
            $expiryRate = ($stats['expiring'] / $stats['active']) * 100;
            $score -= $expiryRate; // 1 point per 1% expiry rate
        }

        // Penalty for expired certificates
        $expiredRate = ($stats['expired'] / $stats['total']) * 100;
        $score -= $expiredRate * 3; // 3 points per 1% expired rate

        return max(0, min(100, round($score)));
    }

    /**
     * Calculate overall system health score
     */
    private function calculateOverallHealthScore(array $providerHealth, array $certStats, array $subStats): int
    {
        $providerScore = 0;
        $healthyProviders = 0;
        
        foreach ($providerHealth as $result) {
            if (($result['status'] ?? '') === 'connected') {
                $healthyProviders++;
            }
        }
        
        if (count($providerHealth) > 0) {
            $providerScore = ($healthyProviders / count($providerHealth)) * 100;
        }

        $certScore = $this->calculateCertificateHealthScore(array_merge($certStats, ['expired' => 0]));
        
        $subScore = 100;
        if ($subStats['total'] > 0) {
            $pastDueRate = ($subStats['past_due'] / $subStats['total']) * 100;
            $subScore -= $pastDueRate * 2;
        }

        // Weighted average: providers 40%, certificates 40%, subscriptions 20%
        return round(($providerScore * 0.4) + ($certScore * 0.4) + ($subScore * 0.2));
    }

    /**
     * Get overall health status from score
     */
    private function getOverallHealthStatus(int $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'poor';
        return 'critical';
    }

    /**
     * Get certificate health status from score
     */
    private function getCertificateHealthStatus(int $score): string
    {
        if ($score >= 95) return 'healthy';
        if ($score >= 80) return 'warning';
        return 'critical';
    }

    /**
     * Calculate provider overall status
     */
    private function calculateProviderOverallStatus(array $healthResults): string
    {
        $healthy = 0;
        $total = count($healthResults);
        
        foreach ($healthResults as $result) {
            if (($result['status'] ?? '') === 'connected') {
                $healthy++;
            }
        }

        if ($healthy === $total) return 'healthy';
        if ($healthy > 0) return 'degraded';
        return 'unhealthy';
    }

    /**
     * Test database connectivity
     */
    private function testDatabaseConnectivity(): array
    {
        try {
            DB::connection()->getPdo();
            Certificate::count(); // Test actual query
            return ['status' => true, 'message' => 'Database connectivity OK'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Database connectivity failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test cache functionality
     */
    private function testCacheFunctionality(): array
    {
        try {
            $testKey = 'ssl_health_test_' . time();
            $testValue = 'test_value';
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrieved === $testValue) {
                return ['status' => true, 'message' => 'Cache functionality OK'];
            } else {
                return ['status' => false, 'message' => 'Cache value mismatch'];
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Cache test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test provider configurations
     */
    private function testProviderConfigurations(): array
    {
        try {
            $errors = [];
            $providers = ['gogetssl', 'google_certificate_manager', 'lets_encrypt'];
            
            foreach ($providers as $provider) {
                $providerErrors = $this->providerFactory->validateProviderConfig($provider);
                if (!empty($providerErrors)) {
                    $errors[$provider] = $providerErrors;
                }
            }

            if (empty($errors)) {
                return ['status' => true, 'message' => 'All provider configurations valid'];
            } else {
                return ['status' => false, 'message' => 'Configuration errors found', 'errors' => $errors];
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Provider configuration test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test certificate processing
     */
    private function testCertificateProcessing(): array
    {
        try {
            // Test if jobs can be dispatched
            $recentJobs = DB::table('jobs')->where('created_at', '>=', now()->subHour())->count();
            $failedJobs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            
            if ($failedJobs > 10) {
                return ['status' => false, 'message' => "Too many failed jobs: {$failedJobs}"];
            }

            return ['status' => true, 'message' => "Job processing OK. Recent jobs: {$recentJobs}, Failed: {$failedJobs}"];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Certificate processing test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test queue system
     */
    private function testQueueSystem(): array
    {
        try {
            $queueSize = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            
            if ($queueSize > 1000) {
                return ['status' => false, 'message' => "Queue backlog too large: {$queueSize}"];
            }

            return ['status' => true, 'message' => "Queue system OK. Pending: {$queueSize}, Failed today: {$failedJobs}"];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Queue system test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test file permissions
     */
    private function testFilePermissions(): array
    {
        try {
            $paths = [
                storage_path('logs'),
                storage_path('app'),
                storage_path('framework/cache'),
            ];

            foreach ($paths as $path) {
                if (!is_writable($path)) {
                    return ['status' => false, 'message' => "Path not writable: {$path}"];
                }
            }

            return ['status' => true, 'message' => 'All required paths are writable'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'File permissions test failed: ' . $e->getMessage()];
        }
    }
}