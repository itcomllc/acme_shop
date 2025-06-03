<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\{CertificateProviderFactory, EnhancedSSLSaaSService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Log, Cache};

/**
 * SSL Provider API Controller
 * 
 * Handles SSL certificate provider management via API endpoints
 */
class SSLProviderController extends Controller
{
    public function __construct(
        private readonly CertificateProviderFactory $providerFactory,
        private readonly EnhancedSSLSaaSService $sslService
    ) {}

    /**
     * Get all available providers with their status
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $providers = $this->providerFactory->getAvailableProviders();
            $capabilities = $this->providerFactory->getProviderCapabilities();
            $providerStatus = $this->providerFactory->getProviderStatus();

            $providersData = [];
            foreach ($providers as $key => $provider) {
                $isConfigured = in_array($key, $providerStatus['available_providers']);
                
                $providersData[] = [
                    'key' => $key,
                    'name' => $provider['name'],
                    'description' => $provider['description'],
                    'features' => $provider['features'],
                    'supported_cas' => $provider['supported_cas'] ?? [],
                    'validation_methods' => $provider['validation_methods'] ?? [],
                    'certificate_formats' => $provider['certificate_formats'] ?? [],
                    'capabilities' => $capabilities[$key] ?? [],
                    'is_configured' => $isConfigured,
                    'is_enabled' => config("ssl-enhanced.providers.{$key}.enabled", false),
                    'priority' => config("ssl-enhanced.providers.{$key}.priority", 999),
                ];
            }

            // Sort by priority
            usort($providersData, fn($a, $b) => $a['priority'] <=> $b['priority']);

            return response()->json([
                'success' => true,
                'data' => [
                    'providers' => $providersData,
                    'summary' => [
                        'total_providers' => count($providers),
                        'configured_providers' => $providerStatus['configured_providers'],
                        'available_providers' => $providerStatus['available_providers'],
                        'unconfigured_providers' => $providerStatus['unconfigured_providers'],
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch SSL providers', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch providers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compare providers feature matrix
     */
    public function compare(Request $request): JsonResponse
    {
        try {
            $comparison = $this->providerFactory->compareProviders();
            
            // Add runtime health check if requested
            if ($request->boolean('include_health')) {
                $healthResults = $this->sslService->performHealthCheck();
                
                foreach ($comparison as $provider => &$data) {
                    $data['health_status'] = $healthResults[$provider] ?? [
                        'status' => 'unknown',
                        'error' => 'Health check not available'
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'comparison' => $comparison,
                    'generated_at' => now()->toISOString(),
                    'health_included' => $request->boolean('include_health')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to compare SSL providers', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to compare providers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get provider recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            $requirements = [
                'certificate_type' => $request->get('certificate_type', 'DV'),
                'auto_renewal' => $request->boolean('auto_renewal'),
                'cost_preference' => $request->get('cost_preference', 'balanced'),
                'hosting_platform' => $request->get('hosting_platform', 'general'),
                'domain_count' => (int) $request->get('domain_count', 1),
                'requires_download' => $request->boolean('requires_download'),
            ];

            // Get general recommendations
            $recommendations = $this->providerFactory->getProviderRecommendations();
            
            // Get specific recommendation for requirements
            $bestProvider = $this->providerFactory->getRecommendedProvider($requirements);
            
            // Score all providers for the requirements
            $scoredProviders = [];
            $availableProviders = $this->providerFactory->getProviderStatus()['available_providers'];
            
            foreach ($availableProviders as $provider) {
                try {
                    $providerInstance = $this->providerFactory->createProvider($provider);
                    $score = $this->calculateProviderScore($provider, $requirements);
                    
                    $scoredProviders[] = [
                        'provider' => $provider,
                        'name' => $recommendations[$provider]['name'] ?? $provider,
                        'score' => $score,
                        'best_for' => $recommendations[$provider]['best_for'] ?? [],
                        'matches_requirements' => $provider === $bestProvider,
                    ];
                } catch (\Exception $e) {
                    // Skip providers that can't be instantiated
                    continue;
                }
            }

            // Sort by score (highest first)
            usort($scoredProviders, fn($a, $b) => $b['score'] <=> $a['score']);

            return response()->json([
                'success' => true,
                'data' => [
                    'requirements' => $requirements,
                    'recommended_provider' => $bestProvider,
                    'scored_providers' => $scoredProviders,
                    'all_recommendations' => $recommendations,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get provider recommendations', [
                'error' => $e->getMessage(),
                'requirements' => $requirements ?? []
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connection to specific provider
     */
    public function testConnection(Request $request, string $provider): JsonResponse
    {
        try {
            // Validate provider
            $availableProviders = array_keys($this->providerFactory->getAvailableProviders());
            if (!in_array($provider, $availableProviders)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid provider specified'
                ], 404);
            }

            $startTime = microtime(true);
            
            // Test provider connection
            $providerInstance = $this->providerFactory->createProvider($provider);
            $connectionTest = $providerInstance->testConnection();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $result = [
                'provider' => $provider,
                'success' => $connectionTest['success'],
                'response_time_ms' => $responseTime,
                'message' => $connectionTest['message'] ?? 'Connection test completed',
                'tested_at' => now()->toISOString(),
            ];

            if (!$connectionTest['success']) {
                $result['error'] = $connectionTest['error'] ?? 'Connection test failed';
            }

            // Additional provider-specific data
            if (isset($connectionTest['account'])) {
                $result['account_info'] = $connectionTest['account'];
            }

            if (isset($connectionTest['project_id'])) {
                $result['project_id'] = $connectionTest['project_id'];
            }

            Log::info('Provider connection test completed', [
                'provider' => $provider,
                'success' => $connectionTest['success'],
                'response_time' => $responseTime
            ]);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Provider connection test failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage(),
                'provider' => $provider
            ], 500);
        }
    }

    /**
     * Get health check status for all providers
     */
    public function healthCheck(Request $request): JsonResponse
    {
        try {
            $healthResults = $this->sslService->performHealthCheck();
            
            $summary = [
                'healthy_providers' => 0,
                'unhealthy_providers' => 0,
                'unknown_providers' => 0,
                'total_providers' => count($healthResults),
            ];

            foreach ($healthResults as $provider => $result) {
                switch ($result['status']) {
                    case 'connected':
                        $summary['healthy_providers']++;
                        break;
                    case 'failed':
                    case 'error':
                        $summary['unhealthy_providers']++;
                        break;
                    default:
                        $summary['unknown_providers']++;
                }
            }

            $overallStatus = 'healthy';
            if ($summary['unhealthy_providers'] > 0) {
                $overallStatus = 'unhealthy';
            } elseif ($summary['unknown_providers'] > 0) {
                $overallStatus = 'warning';
            }

            // Cache health results
            Cache::put('ssl_provider_health_check', [
                'results' => $healthResults,
                'summary' => $summary,
                'overall_status' => $overallStatus,
                'checked_at' => now()->toISOString(),
            ], now()->addMinutes(5));

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_status' => $overallStatus,
                    'summary' => $summary,
                    'providers' => $healthResults,
                    'checked_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Provider health check failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get public provider status (no auth required)
     */
    public function publicStatus(): JsonResponse
    {
        try {
            // Get cached health check results
            $cachedHealth = Cache::get('ssl_provider_health_check');
            
            if (!$cachedHealth) {
                // Run a quick health check if no cache
                $healthResults = $this->sslService->performHealthCheck();
                $overallStatus = 'unknown';
                
                $healthyCount = 0;
                foreach ($healthResults as $result) {
                    if (($result['status'] ?? '') === 'connected') {
                        $healthyCount++;
                    }
                }
                
                $overallStatus = $healthyCount > 0 ? 'healthy' : 'unhealthy';
                
                $cachedHealth = [
                    'overall_status' => $overallStatus,
                    'healthy_providers' => $healthyCount,
                    'total_providers' => count($healthResults),
                    'checked_at' => now()->toISOString(),
                ];
                
                Cache::put('ssl_provider_health_check', $cachedHealth, now()->addMinutes(5));
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_status' => $cachedHealth['overall_status'] ?? 'unknown',
                    'healthy_providers' => $cachedHealth['healthy_providers'] ?? 0,
                    'total_providers' => $cachedHealth['total_providers'] ?? 0,
                    'last_checked' => $cachedHealth['checked_at'] ?? null,
                    'service_operational' => ($cachedHealth['overall_status'] ?? '') === 'healthy',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true, // Don't expose internal errors to public
                'data' => [
                    'overall_status' => 'unknown',
                    'service_operational' => false,
                    'message' => 'Status check temporarily unavailable'
                ]
            ]);
        }
    }

    /**
     * Calculate provider score based on requirements
     */
    private function calculateProviderScore(string $provider, array $requirements): int
    {
        $score = 0;
        $capabilities = $this->providerFactory->getProviderCapabilities()[$provider] ?? [];
        
        // Score based on certificate type support
        if (in_array($requirements['certificate_type'], $capabilities['validation_types'] ?? [])) {
            $score += 25;
        }

        // Score based on auto-renewal requirement
        if ($requirements['auto_renewal'] && ($capabilities['auto_renewal'] ?? false)) {
            $score += 20;
        }

        // Score based on cost preference
        $cost = $capabilities['cost'] ?? 'paid';
        switch ($requirements['cost_preference']) {
            case 'free':
                $score += $cost === 'free' ? 20 : 0;
                break;
            case 'balanced':
                $score += 15;
                break;
            case 'premium':
                $score += $cost === 'paid' ? 20 : 10;
                break;
        }

        // Score based on download requirement
        if ($requirements['requires_download']) {
            if ($capabilities['download_support'] ?? true) {
                $score += 10;
            } else {
                $score -= 15; // Penalty for not supporting download
            }
        }

        // Score based on provider priority
        $priority = config("ssl-enhanced.providers.{$provider}.priority", 999);
        $score += max(0, 10 - $priority);

        // Score based on hosting platform
        if ($requirements['hosting_platform'] === 'gcp' && $provider === 'google_certificate_manager') {
            $score += 25;
        }

        return max(0, $score);
    }
}
