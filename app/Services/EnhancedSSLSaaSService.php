<?php

namespace App\Services;

use App\Models\{User, Subscription, Certificate};
use App\Jobs\ProcessCertificateValidation;
use App\Services\{GoGetSSLService, AcmeService, CertificateProviderFactory};
use Square\SquareClient;
use Square\Types\Address;
use Square\Customers\Requests\{CreateCustomerRequest, SearchCustomersRequest, ListCustomersRequest};
use Square\Exceptions\SquareException;
use Illuminate\Support\Facades\{Log, DB, Cache};

/**
 * Enhanced SSL SaaS Service - Laravel 11 + Square SDK v42+ Implementation
 * 
 * Enhanced version with multi-provider support, improved error handling, and advanced features
 */
class EnhancedSSLSaaSService
{
    private const PLAN_CONFIGS = [
        'basic' => [
            'max_domains' => 1,
            'certificate_type' => 'DV',
            'price' => 999, // $9.99 in cents
            'period' => 'MONTHLY',
            'provider' => CertificateProviderFactory::PROVIDER_LETS_ENCRYPT,
        ],
        'professional' => [
            'max_domains' => 5,
            'certificate_type' => 'OV',
            'price' => 2999, // $29.99 in cents
            'period' => 'MONTHLY',
            'provider' => CertificateProviderFactory::PROVIDER_GOGETSSL,
        ],
        'enterprise' => [
            'max_domains' => 100,
            'certificate_type' => 'EV',
            'price' => 9999, // $99.99 in cents
            'period' => 'MONTHLY',
            'provider' => CertificateProviderFactory::PROVIDER_GOGETSSL,
        ]
    ];

    public function __construct(
        private readonly SquareClient $squareClient,
        private readonly CertificateProviderFactory $providerFactory,
        private readonly AcmeService $acmeService
    ) {}

    /**
     * Create subscription with enhanced multi-provider support
     */
    public function createSubscription(User $user, string $planType, array $domains, string $cardNonce): array
    {
        if (!isset(self::PLAN_CONFIGS[$planType])) {
            throw new \InvalidArgumentException("Invalid plan type: {$planType}");
        }

        $plan = self::PLAN_CONFIGS[$planType];

        if (count($domains) > $plan['max_domains']) {
            throw new \Exception('Domain limit exceeded for this plan');
        }

        return DB::transaction(function () use ($user, $planType, $plan, $domains, $cardNonce) {
            try {
                // 1. Create or get Square customer with enhanced error handling
                $customer = $this->createOrGetSquareCustomer($user);

                // 2. Validate provider is available
                $this->validateProviderAvailability($plan['provider']);

                // 3. Create local subscription record
                $subscription = $this->createLocalSubscription($user, $customer['id'], $planType, $plan, $domains);

                // 4. Issue certificates using the appropriate provider
                $certificates = [];
                foreach ($domains as $domain) {
                    try {
                        $certificate = $this->issueCertificateWithProvider(
                            $subscription, 
                            $domain, 
                            $plan['provider']
                        );
                        $certificates[] = $certificate;
                    } catch (\Exception $e) {
                        Log::warning('Failed to issue certificate during subscription creation', [
                            'subscription_id' => $subscription->id,
                            'domain' => $domain,
                            'provider' => $plan['provider'],
                            'error' => $e->getMessage()
                        ]);
                        // Continue with other domains, don't fail entire subscription
                    }
                }

                // 5. Cache subscription data for performance
                $this->cacheSubscriptionData($subscription);

                return [
                    'success' => true,
                    'subscription' => $subscription,
                    'customer_id' => $customer['id'],
                    'certificates' => $certificates,
                    'provider' => $plan['provider']
                ];

            } catch (\Throwable $e) {
                Log::error('Enhanced subscription creation failed', [
                    'user_id' => $user->id,
                    'plan_type' => $planType,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                throw $e;
            }
        });
    }

    /**
     * Enhanced customer creation with proper Square SDK v42+ usage
     */
    private function createOrGetSquareCustomer(User $user): array
    {
        try {
            $request = new ListCustomersRequest([
                'limit' => 1,
                'query' => [
                    'filter' => [
                        'emailAddress' => [
                            'exact' => $user->email
                        ]
                    ]
                ]
            ]);

            /** @var Square\Core\Pagination\Pager */
            $response = $this->squareClient->customers->list($request);
            $customers = $response->getCustomers();

            if (!empty($customers)) {
                return [
                    'id' => $customers[0]   ->getId(),
                    'existing' => true
                ];
            }

            // Create new customer with proper request structure
            $address = new Address();
            $address->setAddressLine1($user->address ?? '');
            $address->setLocality($user->city ?? '');
            $address->setAdministrativeDistrictLevel1($user->state ?? '');
            $address->setPostalCode($user->postal_code ?? '');
            $address->setCountry($user->country ?? 'US');

            $createRequest = new CreateCustomerRequest();
            $createRequest->setGivenName($user->first_name ?? $user->name ?? 'SSL SaaS User');
            $createRequest->setFamilyName($user->last_name ?? '');
            $createRequest->setEmailAddress($user->email);
            $createRequest->setPhoneNumber($user->phone ?? '');
            $createRequest->setAddress($address);
            $createRequest->setNote('Enhanced SSL SaaS Platform Customer');

            /** @var \Square\Models\CreateCustomerResponse */
            $createResponse = $this->squareClient->customers->create($createRequest);

            if (!$createResponse->isSuccess()) {
                $errors = $createResponse->getErrors();
                throw new \Exception('Failed to create customer: ' . json_encode($errors));
            }

            $customer = $createResponse->getCustomer();

            // Update user with Square customer ID
            $user->update(['square_customer_id' => $customer->getId()]);

            return [
                'id' => $customer->getId(),
                'existing' => false
            ];

        } catch (SquareException $e) {
            throw new \Exception("Square API error: {$e->getMessage()}");
        }
    }

    /**
     * Validate provider availability and configuration
     */
    private function validateProviderAvailability(string $provider): void
    {
        $errors = $this->providerFactory->validateProviderConfig($provider);
        
        if (!empty($errors)) {
            throw new \Exception("Provider {$provider} is not properly configured: " . implode(', ', $errors));
        }

        // Test provider connectivity
        try {
            $providerInstance = $this->providerFactory->createProvider($provider);
            if (method_exists($providerInstance, 'testConnection')) {
                $connectionTest = $providerInstance->testConnection();
                if (!$connectionTest['success']) {
                    throw new \Exception("Provider {$provider} connection failed: " . ($connectionTest['error'] ?? 'Unknown error'));
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("Provider {$provider} is not available: " . $e->getMessage());
        }
    }

    /**
     * Issue certificate using specified provider
     */
    public function issueCertificateWithProvider(Subscription $subscription, string $domain, ?string $providerType = null): Certificate
    {
        $provider = $providerType ?? $this->getProviderForSubscription($subscription);
        
        Log::info('Issuing certificate with enhanced service', [
            'subscription_id' => $subscription->id,
            'domain' => $domain,
            'provider' => $provider
        ]);

        switch ($provider) {
            case CertificateProviderFactory::PROVIDER_GOGETSSL:
                return $this->issueCertificateWithGoGetSSL($subscription, $domain);
                
            case CertificateProviderFactory::PROVIDER_GOOGLE_CERTIFICATE_MANAGER:
                return $this->issueCertificateWithGoogleCM($subscription, $domain);
                
            case CertificateProviderFactory::PROVIDER_LETS_ENCRYPT:
                return $this->issueCertificateWithLetsEncrypt($subscription, $domain);
                
            default:
                throw new \InvalidArgumentException("Unsupported provider: {$provider}");
        }
    }

    /**
     * Issue certificate with GoGetSSL
     */
    private function issueCertificateWithGoGetSSL(Subscription $subscription, string $domain): Certificate
    {
        // Create ACME order for domain validation
        $acmeOrder = $this->acmeService->createOrder([
            'identifiers' => [['type' => 'dns', 'value' => $domain]],
            'profile' => 'tlsserver'
        ]);

        // Create certificate record
        $certificate = Certificate::create([
            'subscription_id' => $subscription->id,
            'domain' => $domain,
            'type' => $subscription->certificate_type,
            'status' => 'pending_validation',
            'acme_order_id' => $acmeOrder->id,
            'expires_at' => now()->addDays(365), // GoGetSSL certificates typically 1 year
            'provider' => CertificateProviderFactory::PROVIDER_GOGETSSL
        ]);

        // Start certificate processing
        ProcessCertificateValidation::dispatch($certificate);

        return $certificate;
    }

    /**
     * Issue certificate with Google Certificate Manager
     */
    private function issueCertificateWithGoogleCM(Subscription $subscription, string $domain): Certificate
    {
        /** @var GoogleCertificateManagerService */
        $googleCM = $this->providerFactory->createGoogleCertificateManagerProvider();

        // Create certificate record
        $certificate = Certificate::create([
            'subscription_id' => $subscription->id,
            'domain' => $domain,
            'type' => 'DV', // Google CM only supports DV
            'status' => 'pending_validation',
            'expires_at' => now()->addDays(90), // Google managed certificates
            'provider' => CertificateProviderFactory::PROVIDER_GOOGLE_CERTIFICATE_MANAGER
        ]);

        // Initiate Google Certificate Manager certificate creation
        try {
            $gcmResult = $googleCM->createManagedCertificate([$domain], [
                'subscription_id' => $subscription->id,
                'certificate_id' => $certificate->id
            ]);

            $certificate->update([
                'provider_certificate_id' => $gcmResult['certificate_id'] ?? null,
                'provider_data' => $gcmResult,
                'status' => 'processing'
            ]);

        } catch (\Exception $e) {
            $certificate->update(['status' => 'failed']);
            throw $e;
        }

        return $certificate;
    }

    /**
     * Issue certificate with Let's Encrypt (via ACME)
     */
    private function issueCertificateWithLetsEncrypt(Subscription $subscription, string $domain): Certificate
    {
        // Create ACME order for Let's Encrypt
        $acmeOrder = $this->acmeService->createOrder([
            'identifiers' => [['type' => 'dns', 'value' => $domain]],
            'profile' => 'tlsserver'
        ]);

        // Create certificate record
        $certificate = Certificate::create([
            'subscription_id' => $subscription->id,
            'domain' => $domain,
            'type' => 'DV', // Let's Encrypt only supports DV
            'status' => 'pending_validation',
            'acme_order_id' => $acmeOrder->id,
            'expires_at' => now()->addDays(90), // Let's Encrypt certificates are 90 days
            'provider' => CertificateProviderFactory::PROVIDER_LETS_ENCRYPT
        ]);

        // Process ACME validation
        ProcessCertificateValidation::dispatch($certificate);

        return $certificate;
    }

    /**
     * Get provider for subscription based on plan
     */
    private function getProviderForSubscription(Subscription $subscription): string
    {
        $plan = self::PLAN_CONFIGS[$subscription->plan_type] ?? null;
        return $plan['provider'] ?? CertificateProviderFactory::PROVIDER_GOGETSSL;
    }

    /**
     * Enhanced subscription data creation
     */
    private function createLocalSubscription(User $user, string $customerId, string $planType, array $plan, array $domains): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id,
            'square_customer_id' => $customerId,
            'plan_type' => $planType,
            'status' => 'active',
            'max_domains' => $plan['max_domains'],
            'certificate_type' => $plan['certificate_type'],
            'billing_period' => $plan['period'],
            'price' => $plan['price'],
            'domains' => $domains,
            'next_billing_date' => now()->addMonth(),
            'provider' => $plan['provider'],
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Cache subscription data for performance
     */
    private function cacheSubscriptionData(Subscription $subscription): void
    {
        $cacheKey = "subscription:{$subscription->id}";
        $cacheData = [
            'id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'plan_type' => $subscription->plan_type,
            'status' => $subscription->status,
            'max_domains' => $subscription->max_domains,
            'provider' => $subscription->provider ?? 'gogetssl'
        ];

        Cache::put($cacheKey, $cacheData, now()->addHours(1));
    }

    /**
     * Get subscription from cache or database
     */
    public function getSubscription(int $subscriptionId): ?Subscription
    {
        $cacheKey = "subscription:{$subscriptionId}";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($subscriptionId) {
            return Subscription::find($subscriptionId);
        });
    }

    /**
     * Enhanced certificate renewal with provider-specific logic
     */
    public function renewCertificate(Certificate $certificate): array
    {
        if (!$certificate->subscription->isActive()) {
            throw new \Exception('Subscription is not active');
        }

        $provider = $certificate->provider ?? $this->getProviderForSubscription($certificate->subscription);

        try {
            $newCertificate = $this->issueCertificateWithProvider(
                $certificate->subscription,
                $certificate->domain,
                $provider
            );

            // Mark old certificate as replaced
            $certificate->update([
                'status' => 'replaced',
                'replaced_by' => $newCertificate->id,
                'replaced_at' => now()
            ]);

            Log::info('Certificate renewed successfully', [
                'old_certificate_id' => $certificate->id,
                'new_certificate_id' => $newCertificate->id,
                'domain' => $certificate->domain,
                'provider' => $provider
            ]);

            return [
                'success' => true,
                'old_certificate' => $certificate,
                'new_certificate' => $newCertificate,
                'provider' => $provider
            ];

        } catch (\Exception $e) {
            Log::error('Certificate renewal failed', [
                'certificate_id' => $certificate->id,
                'domain' => $certificate->domain,
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get provider statistics
     */
    public function getProviderStatistics(): array
    {
        $stats = [];
        $providers = $this->providerFactory->getAvailableProviders();

        foreach (array_keys($providers) as $provider) {
            $stats[$provider] = [
                'total_certificates' => Certificate::where('provider', $provider)->count(),
                'active_certificates' => Certificate::where('provider', $provider)
                    ->where('status', 'issued')
                    ->count(),
                'pending_certificates' => Certificate::where('provider', $provider)
                    ->where('status', 'pending_validation')
                    ->count(),
                'failed_certificates' => Certificate::where('provider', $provider)
                    ->where('status', 'failed')
                    ->count(),
                'expiring_soon' => Certificate::where('provider', $provider)
                    ->where('status', 'issued')
                    ->where('expires_at', '<=', now()->addDays(30))
                    ->count()
            ];
        }

        return $stats;
    }

    /**
     * Health check for all providers
     */
    public function performHealthCheck(): array
    {
        return $this->providerFactory->testAllProviders();
    }

    /**
     * Revoke certificate with provider-specific handling
     */
    public function revokeCertificate(Certificate $certificate, string $reason = 'unspecified'): array
    {
        try {
            Log::info('Starting certificate revocation', [
                'certificate_id' => $certificate->id,
                'domain' => $certificate->domain,
                'provider' => $certificate->provider,
                'reason' => $reason
            ]);

            // Check if certificate can be revoked
            if ($certificate->isRevoked()) {
                return [
                    'success' => false,
                    'error' => 'Certificate is already revoked'
                ];
            }

            if ($certificate->status !== Certificate::STATUS_ISSUED) {
                return [
                    'success' => false,
                    'error' => 'Only issued certificates can be revoked'
                ];
            }

            // Revoke with appropriate provider
            $revocationResult = match ($certificate->provider) {
                CertificateProviderFactory::PROVIDER_GOGETSSL => $this->revokeGoGetSSLCertificate($certificate, $reason),
                CertificateProviderFactory::PROVIDER_GOOGLE_CERTIFICATE_MANAGER => $this->revokeGoogleCMCertificate($certificate, $reason),
                CertificateProviderFactory::PROVIDER_LETS_ENCRYPT => $this->revokeLetsEncryptCertificate($certificate, $reason),
                default => throw new \Exception("Unsupported provider: {$certificate->provider}")
            };

            if ($revocationResult['success']) {
                // Update local certificate record
                $certificate->update([
                    'status' => Certificate::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revocation_reason' => $reason,
                    'provider_data' => array_merge(
                        $certificate->provider_data ?? [],
                        $revocationResult
                    )
                ]);

                Log::info('Certificate revoked successfully', [
                    'certificate_id' => $certificate->id,
                    'domain' => $certificate->domain,
                    'provider' => $certificate->provider,
                    'reason' => $reason
                ]);

                return [
                    'success' => true,
                    'certificate' => $certificate,
                    'provider' => $certificate->provider,
                    'revocation_data' => $revocationResult
                ];
            } else {
                Log::error('Certificate revocation failed', [
                    'certificate_id' => $certificate->id,
                    'provider' => $certificate->provider,
                    'error' => $revocationResult['error'] ?? 'Unknown error'
                ]);

                return $revocationResult;
            }

        } catch (\Exception $e) {
            Log::error('Certificate revocation failed with exception', [
                'certificate_id' => $certificate->id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Revoke GoGetSSL certificate
     */
    private function revokeGoGetSSLCertificate(Certificate $certificate, string $reason): array
    {
        try {
            /** @var GoGetSSLService */
            $goGetSSLService = $this->providerFactory->createGoGetSSLProvider();
            
            $orderId = $certificate->provider_certificate_id ?? $certificate->gogetssl_order_id;
            
            if (!$orderId) {
                throw new \Exception('GoGetSSL order ID not found');
            }

            $result = $goGetSSLService->revokeCertificate((int) $orderId, $reason);
            
            return [
                'success' => $result['success'] ?? false,
                'provider_response' => $result,
                'revoked_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Revoke Google Certificate Manager certificate
     */
    private function revokeGoogleCMCertificate(Certificate $certificate, string $reason): array
    {
        try {
            /** @var GoogleCertificateManagerService */
            $googleCM = $this->providerFactory->createGoogleCertificateManagerProvider();
            
            if (!$certificate->provider_certificate_id) {
                throw new \Exception('Google Certificate Manager certificate ID not found');
            }

            // Google Certificate Manager revokes by deleting the certificate
            $result = $googleCM->deleteCertificate($certificate->provider_certificate_id);
            
            return [
                'success' => $result['success'] ?? false,
                'provider_response' => $result,
                'revoked_at' => now()->toISOString(),
                'note' => 'Certificate deleted from Google Certificate Manager'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Revoke Let's Encrypt certificate
     */
    private function revokeLetsEncryptCertificate(Certificate $certificate, string $reason): array
    {
        try {
            // Let's Encrypt revocation would be handled through ACME protocol
            // For now, we'll mark it as revoked locally
            // In a full implementation, this would use the ACME service to revoke
            
            Log::info('Let\'s Encrypt certificate revocation initiated', [
                'certificate_id' => $certificate->id,
                'domain' => $certificate->domain,
                'reason' => $reason
            ]);

            // TODO: Implement ACME revocation
            // $acmeService = $this->acmeService;
            // $result = $acmeService->revokeCertificate($certificate->acme_order_id, $reason);

            return [
                'success' => true,
                'provider_response' => ['message' => 'Certificate marked as revoked locally'],
                'revoked_at' => now()->toISOString(),
                'note' => 'ACME revocation to be implemented'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}