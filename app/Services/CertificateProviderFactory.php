<?php

namespace App\Services;

use App\Services\{GoGetSSLService, GoogleCertificateManagerService};
use Illuminate\Support\Facades\Log;

/**
 * Certificate Provider Factory
 * 
 * Factory class to create and manage different SSL certificate providers
 */
class CertificateProviderFactory
{
    /**
     * Available provider types
     */
    public const PROVIDER_GOGETSSL = 'gogetssl';
    public const PROVIDER_GOOGLE_CERTIFICATE_MANAGER = 'google_certificate_manager';
    public const PROVIDER_LETS_ENCRYPT = 'lets_encrypt';

    /**
     * Create GoGetSSL provider instance
     */
    public function createGoGetSSLProvider(): GoGetSSLService
    {
        try {
            return new GoGetSSLService();
        } catch (\Exception $e) {
            Log::error('Failed to create GoGetSSL provider', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to initialize GoGetSSL provider: ' . $e->getMessage());
        }
    }

    /**
     * Create Google Certificate Manager provider instance
     */
    public function createGoogleCertificateManagerProvider(): GoogleCertificateManagerService
    {
        try {
            return new GoogleCertificateManagerService();
        } catch (\Exception $e) {
            Log::error('Failed to create Google Certificate Manager provider', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to initialize Google Certificate Manager provider: ' . $e->getMessage());
        }
    }

    /**
     * Create provider by type
     */
    public function createProvider(string $type): object
    {
        return match ($type) {
            self::PROVIDER_GOGETSSL => $this->createGoGetSSLProvider(),
            self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER => $this->createGoogleCertificateManagerProvider(),
            default => throw new \InvalidArgumentException("Unsupported provider type: {$type}")
        };
    }

    /**
     * Get all available provider types
     * 
     * @return array<string, array{name: string, description: string, features: array<string>}>
     */
    public function getAvailableProviders(): array
    {
        return [
            self::PROVIDER_GOGETSSL => [
                'name' => 'GoGetSSL',
                'description' => 'Commercial SSL certificate provider with multiple CA partners',
                'features' => [
                    'Domain Validation (DV)',
                    'Organization Validation (OV)',
                    'Extended Validation (EV)',
                    'Wildcard Certificates',
                    'Multi-Domain SAN',
                    'Multiple CA Options',
                    'API Management',
                    'Manual Validation'
                ],
                'supported_cas' => ['Sectigo', 'GeoTrust', 'Thawte', 'RapidSSL'],
                'validation_methods' => ['email', 'dns', 'file'],
                'certificate_formats' => ['PEM', 'P7B', 'P12']
            ],
            self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER => [
                'name' => 'Google Certificate Manager',
                'description' => 'Google Cloud Platform managed SSL certificate service',
                'features' => [
                    'Domain Validation (DV)',
                    'Automatic Renewal',
                    'Load Balancer Integration',
                    'DNS Authorization',
                    'Managed Certificates',
                    'GCP Integration',
                    'Zero-maintenance'
                ],
                'supported_cas' => ['Google Trust Services'],
                'validation_methods' => ['dns'],
                'certificate_formats' => ['Google Managed']
            ],
            self::PROVIDER_LETS_ENCRYPT => [
                'name' => "Let's Encrypt",
                'description' => 'Free, automated SSL certificate authority',
                'features' => [
                    'Domain Validation (DV)',
                    'Automatic Renewal',
                    'Free Certificates',
                    'ACME Protocol',
                    'Wildcard Support',
                    'Rate Limited',
                    '90-day Validity'
                ],
                'supported_cas' => ["Let's Encrypt"],
                'validation_methods' => ['dns', 'http'],
                'certificate_formats' => ['PEM']
            ]
        ];
    }

    /**
     * Get provider capabilities
     * 
     * @return array<string, array{validation_types: array<string>, max_validity_days: int, auto_renewal: bool, cost: string}>
     */
    public function getProviderCapabilities(): array
    {
        return [
            self::PROVIDER_GOGETSSL => [
                'validation_types' => ['DV', 'OV', 'EV'],
                'max_validity_days' => 365,
                'auto_renewal' => false,
                'cost' => 'Paid',
                'wildcard_support' => true,
                'san_support' => true,
                'api_access' => true,
                'manual_validation' => true
            ],
            self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER => [
                'validation_types' => ['DV'],
                'max_validity_days' => 90,
                'auto_renewal' => true,
                'cost' => 'Paid (monthly)',
                'wildcard_support' => true,
                'san_support' => true,
                'api_access' => true,
                'manual_validation' => false
            ],
            self::PROVIDER_LETS_ENCRYPT => [
                'validation_types' => ['DV'],
                'max_validity_days' => 90,
                'auto_renewal' => true,
                'cost' => 'Free',
                'wildcard_support' => true,
                'san_support' => true,
                'api_access' => true,
                'manual_validation' => false
            ]
        ];
    }

    /**
     * Test all providers connectivity
     * 
     * @return array<string, array{provider: string, status: string, response_time: float, error: ?string}>
     */
    public function testAllProviders(): array
    {
        $results = [];
        $providers = [
            self::PROVIDER_GOGETSSL,
            self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER
        ];

        foreach ($providers as $providerType) {
            $startTime = microtime(true);
            $result = [
                'provider' => $providerType,
                'status' => 'unknown',
                'response_time' => 0,
                'error' => null
            ];

            try {
                $provider = $this->createProvider($providerType);
                
                // Test connection if method exists
                if (method_exists($provider, 'testConnection')) {
                    $connectionTest = $provider->testConnection();
                    $result['status'] = $connectionTest['success'] ? 'connected' : 'failed';
                    if (!$connectionTest['success']) {
                        $result['error'] = $connectionTest['error'] ?? 'Connection failed';
                    }
                } else {
                    $result['status'] = 'no_test_method';
                }

                $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);

            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['error'] = $e->getMessage();
                $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
            }

            $results[$providerType] = $result;
        }

        return $results;
    }

    /**
     * Get recommended provider for specific requirements
     * 
     * @param array{validation_type?: string, auto_renewal?: bool, cost_preference?: string, hosting_platform?: string} $requirements
     */
    public function getRecommendedProvider(array $requirements = []): string
    {
        $validationType = $requirements['validation_type'] ?? 'DV';
        $autoRenewal = $requirements['auto_renewal'] ?? false;
        $costPreference = $requirements['cost_preference'] ?? 'balanced'; // free, balanced, premium
        $hostingPlatform = $requirements['hosting_platform'] ?? 'general';

        // Google Cloud Platform preference
        if ($hostingPlatform === 'gcp' || $hostingPlatform === 'google_cloud') {
            return self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER;
        }

        // Free cost preference
        if ($costPreference === 'free') {
            return self::PROVIDER_LETS_ENCRYPT;
        }

        // OV/EV validation requirement
        if (in_array($validationType, ['OV', 'EV'])) {
            return self::PROVIDER_GOGETSSL;
        }

        // Auto-renewal preference for DV certificates
        if ($autoRenewal && $validationType === 'DV') {
            return self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER;
        }

        // Default to GoGetSSL for general flexibility
        return self::PROVIDER_GOGETSSL;
    }

    /**
     * Compare providers feature matrix
     * 
     * @return array<string, array<string, mixed>>
     */
    public function compareProviders(): array
    {
        $providers = $this->getAvailableProviders();
        $capabilities = $this->getProviderCapabilities();
        
        $comparison = [];
        
        foreach ($providers as $type => $info) {
            $comparison[$type] = array_merge($info, $capabilities[$type] ?? []);
        }
        
        return $comparison;
    }

    /**
     * Validate provider configuration
     */
    public function validateProviderConfig(string $providerType): array
    {
        $errors = [];

        switch ($providerType) {
            case self::PROVIDER_GOGETSSL:
                if (empty(config('services.gogetssl.username'))) {
                    $errors[] = 'GoGetSSL username is not configured';
                }
                if (empty(config('services.gogetssl.password'))) {
                    $errors[] = 'GoGetSSL password is not configured';
                }
                break;

            case self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER:
                if (empty(config('services.google.project_id'))) {
                    $errors[] = 'Google Cloud project ID is not configured';
                }
                if (empty(config('services.google.key_file_path')) && empty(config('services.google.credentials'))) {
                    $errors[] = 'Google Cloud credentials are not configured';
                }
                break;

            case self::PROVIDER_LETS_ENCRYPT:
                // Let's Encrypt typically doesn't require API credentials
                // but might need ACME client configuration
                break;

            default:
                $errors[] = "Unknown provider type: {$providerType}";
        }

        return $errors;
    }

    /**
     * Get provider status summary
     * 
     * @return array{total_providers: int, configured_providers: int, available_providers: array<string>}
     */
    public function getProviderStatus(): array
    {
        $allProviders = array_keys($this->getAvailableProviders());
        $configuredProviders = [];

        foreach ($allProviders as $provider) {
            $errors = $this->validateProviderConfig($provider);
            if (empty($errors)) {
                $configuredProviders[] = $provider;
            }
        }

        return [
            'total_providers' => count($allProviders),
            'configured_providers' => count($configuredProviders),
            'available_providers' => $configuredProviders,
            'unconfigured_providers' => array_diff($allProviders, $configuredProviders)
        ];
    }

    /**
     * Get best provider based on requirements
     * 
     * @param array{domains?: array, certificate_type?: string, preferred_provider?: string, requires_download?: bool, auto_managed?: bool, budget_constraint?: string} $requirements
     */
    public function getBestProvider(array $requirements): object
    {
        $preferredProvider = $requirements['preferred_provider'] ?? null;
        $certificateType = $requirements['certificate_type'] ?? 'DV';
        $requiresDownload = $requirements['requires_download'] ?? false;
        $autoManaged = $requirements['auto_managed'] ?? false;
        $budgetConstraint = $requirements['budget_constraint'] ?? 'medium';

        // If specific provider is preferred and available, use it
        if ($preferredProvider && $this->isProviderAvailable($preferredProvider)) {
            return $this->createProvider($preferredProvider);
        }

        // Auto-select based on requirements
        $availableProviders = $this->getProviderStatus()['available_providers'];
        
        // Filter providers based on requirements
        $suitableProviders = [];
        
        foreach ($availableProviders as $provider) {
            $capabilities = $this->getProviderCapabilities()[$provider] ?? [];
            
            // Check certificate type support
            if (!in_array($certificateType, $capabilities['validation_types'] ?? [])) {
                continue;
            }

            // Check download requirement
            if ($requiresDownload && !($capabilities['download_support'] ?? true)) {
                continue;
            }

            // Check auto-management preference
            if ($autoManaged && !($capabilities['auto_renewal'] ?? false)) {
                continue;
            }

            // Add provider with score
            $score = $this->calculateProviderScore($provider, $requirements);
            $suitableProviders[] = ['provider' => $provider, 'score' => $score];
        }

        if (empty($suitableProviders)) {
            throw new \Exception('No suitable providers available for the given requirements');
        }

        // Sort by score (highest first)
        usort($suitableProviders, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $bestProvider = $suitableProviders[0]['provider'];
        return $this->createProvider($bestProvider);
    }

    /**
     * Calculate provider score based on requirements
     */
    private function calculateProviderScore(string $provider, array $requirements): int
    {
        $score = 0;
        $capabilities = $this->getProviderCapabilities()[$provider] ?? [];
        $budget = $requirements['budget_constraint'] ?? 'medium';

        // Score based on cost preference
        $cost = $capabilities['cost'] ?? 'paid';
        if ($budget === 'low' && $cost === 'free') {
            $score += 20;
        } elseif ($budget === 'high' && $cost === 'paid') {
            $score += 15;
        } elseif ($budget === 'medium') {
            $score += 10;
        }

        // Score based on auto-renewal capability
        if ($capabilities['auto_renewal'] ?? false) {
            $score += 15;
        }

        // Score based on provider priority from config
        $providerConfig = config('ssl-enhanced.providers.' . $provider, []);
        $priority = $providerConfig['priority'] ?? 999;
        $score += max(0, 10 - $priority); // Lower priority number = higher score

        // Score based on feature completeness
        $features = $capabilities['features'] ?? [];
        $score += count($features);

        return $score;
    }

    /**
     * Check if provider is available and configured
     */
    private function isProviderAvailable(string $provider): bool
    {
        $errors = $this->validateProviderConfig($provider);
        return empty($errors);
    }

    /**
     * Get provider health status for all providers
     */
    public function getProviderHealthStatus(): array
    {
        return $this->testAllProviders();
    }

    /**
     * Validate domains across all available providers
     */
    public function validateDomainsAcrossProviders(array $domains): array
    {
        $results = [];
        $availableProviders = $this->getProviderStatus()['available_providers'];

        foreach ($availableProviders as $provider) {
            try {
                $providerInstance = $this->createProvider($provider);
                if (method_exists($providerInstance, 'validateDomains')) {
                    $results[$provider] = $providerInstance->validateDomains($domains);
                } else {
                    $results[$provider] = ['valid' => true, 'errors' => [], 'warnings' => []];
                }
            } catch (\Exception $e) {
                $results[$provider] = [
                    'valid' => false,
                    'errors' => [$e->getMessage()],
                    'warnings' => []
                ];
            }
        }

        return $results;
    }

    /**
     * Get provider comparison matrix
     */
    public function getProviderComparison(): array
    {
        return $this->compareProviders();
    }

    /**
     * Get provider recommendations based on usage patterns
     */
    public function getProviderRecommendations(): array
    {
        $availableProviders = $this->getProviderStatus()['available_providers'];
        $recommendations = [];

        foreach ($availableProviders as $provider) {
            $capabilities = $this->getProviderCapabilities()[$provider] ?? [];
            $providerInfo = $this->getAvailableProviders()[$provider] ?? [];

            $recommendations[$provider] = [
                'name' => $providerInfo['name'] ?? $provider,
                'description' => $providerInfo['description'] ?? '',
                'best_for' => $this->getProviderBestUseCase($provider),
                'cost' => $capabilities['cost'] ?? 'unknown',
                'auto_renewal' => $capabilities['auto_renewal'] ?? false,
                'supported_types' => $capabilities['validation_types'] ?? [],
                'features' => $providerInfo['features'] ?? []
            ];
        }

        return $recommendations;
    }

    /**
     * Get best use case for provider
     */
    private function getProviderBestUseCase(string $provider): array
    {
        return match ($provider) {
            self::PROVIDER_GOGETSSL => [
                'Traditional SSL needs',
                'OV/EV certificates',
                'Manual certificate management',
                'Multi-vendor environments'
            ],
            self::PROVIDER_GOOGLE_CERTIFICATE_MANAGER => [
                'Google Cloud deployments',
                'Automatic certificate management',
                'Load balancer integration',
                'Zero-maintenance SSL'
            ],
            self::PROVIDER_LETS_ENCRYPT => [
                'Cost-conscious deployments',
                'Development environments',
                'Automated certificate management',
                'High-volume certificate needs'
            ],
            default => ['General SSL certificate needs']
        };
    }
}