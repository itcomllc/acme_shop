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
}