<?php

namespace App\Services;

use App\Models\{User, Subscription, Certificate};
use App\Jobs\{RevokeCertificate, NotifyPaymentFailed, SendPaymentConfirmation, ProcessCertificateValidation};
use App\Contracts\CertificateProviderInterface;
use Square\SquareClient;
use Square\Types\Address;
use Square\Customers\Requests\{SearchCustomersRequest, CreateCustomerRequest, UpdateCustomerRequest};
use Square\Exceptions\SquareException;
use Illuminate\Support\Facades\{Log, DB};
use Carbon\Carbon;

/**
 * Enhanced SSL SaaS Service with Multiple Certificate Providers
 * Supports GoGetSSL, Google Certificate Manager, and future providers
 */
class EnhancedSSLSaaSService
{
    private const PLAN_CONFIGS = [
        'basic' => [
            'max_domains' => 1,
            'certificate_type' => 'DV',
            'price' => 999, // $9.99 in cents
            'period' => 'MONTHLY',
            'provider_preference' => 'gogetssl',
        ],
        'professional' => [
            'max_domains' => 5,
            'certificate_type' => 'DV',
            'price' => 2999, // $29.99 in cents
            'period' => 'MONTHLY',
            'provider_preference' => 'auto',
        ],
        'enterprise' => [
            'max_domains' => 100,
            'certificate_type' => 'DV',
            'price' => 9999, // $99.99 in cents
            'period' => 'MONTHLY',
            'provider_preference' => 'google_certificate_manager',
        ]
    ];

    public function __construct(
        private readonly SquareClient $squareClient,
        private readonly CertificateProviderFactory $providerFactory
    ) {}

    /**
     * Create subscription with enhanced provider selection
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
                // 1. Create or get Square customer
                $customer = $this->createOrGetSquareCustomer($user);

                // 2. Create local subscription record
                $subscription = $this->createLocalSubscription($user, $customer['id'], $planType, $plan, $domains);

                // 3. Issue certificates with appropriate providers
                $certificateResults = [];
                foreach ($domains as $domain) {
                    $certResult = $this->issueCertificateWithBestProvider($subscription, $domain);
                    $certificateResults[] = $certResult;
                }

                return [
                    'success' => true,
                    'subscription' => $subscription,
                    'customer_id' => $customer['id'],
                    'certificates' => $certificateResults
                ];

            } catch (\Throwable $e) {
                Log::error('Failed to create subscription', [
                    'user_id' => $user->id,
                    'plan_type' => $planType,
                    'error' => $e->getMessage()
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Issue certificate using the best available provider
     */
    public function issueCertificateWithBestProvider(Subscription $subscription, string $domain): array
    {
        $plan = self::PLAN_CONFIGS[$subscription->plan_type];
        
        $requirements = [
            'domains' => [$domain],
            'certificate_type' => $plan['certificate_type'],
            'preferred_provider' => $plan['provider_preference'] === 'auto' ? null : $plan['provider_preference'],
            'requires_download' => $this->requiresCertificateDownload($subscription),
            'auto_managed' => $this->prefersAutoManagement($subscription),
            'budget_constraint' => $this->getBudgetConstraint($subscription->plan_type)
        ];

        try {
            // Get best provider for requirements
            $provider = $this->providerFactory->getBestProvider($requirements);
            
            // Create certificate record first
            $certificate = Certificate::create([
                'subscription_id' => $subscription->id,
                'domain' => $domain,
                'type' => $plan['certificate_type'],
                'status' => 'pending_validation',
                'provider' => $provider->getProviderName(),
                'provider_data' => [],
                'expires_at' => now()->addDays(90)
            ]);

            // Issue certificate through provider
            $providerResult = $provider->createCertificate([$domain], [
                'certificate_type' => $plan['certificate_type'],
                'contact_email' => $subscription->user->email,
                'admin_email' => $subscription->user->email,
                'test_mode' => config('ssl.development.test_mode', false)
            ]);

            if ($providerResult['success']) {
                // Update certificate with provider data
                $certificate->update([
                    'provider_certificate_id' => $providerResult['certificate_id'],
                    'provider_data' => $providerResult['provider_data'] ?? [],
                    'status' => $providerResult['status'] ?? 'pending_validation'
                ]);

                // Schedule validation monitoring
                $this->scheduleValidationMonitoring($certificate, $provider);

                Log::info('Certificate issuance initiated', [
                    'subscription_id' => $subscription->id,
                    'domain' => $domain,
                    'certificate_id' => $certificate->id,
                    'provider' => $provider->getProviderName()
                ]);

                return [
                    'success' => true,
                    'certificate' => $certificate,
                    'provider' => $provider->getProviderName(),
                    'provider_result' => $providerResult
                ];
            } else {
                // Provider failed, try fallback if enabled
                if (config('ssl.redundancy.enable_provider_fallback', true)) {
                    return $this->tryFallbackProvider($certificate, $domain, $requirements);
                }

                $certificate->update(['status' => 'failed']);
                
                return [
                    'success' => false,
                    'error' => $providerResult['error'] ?? 'Certificate issuance failed',
                    'certificate' => $certificate
                ];
            }

        } catch (\Exception $e) {
            Log::error('Certificate issuance failed', [
                'subscription_id' => $subscription->id,
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Try fallback provider if primary fails
     */
    private function tryFallbackProvider(Certificate $certificate, string $domain, array $requirements): array
    {
        $fallbackOrder = config('ssl.redundancy.fallback_order', []);
        $currentProvider = $certificate->provider;

        // Remove current provider from fallback options
        $availableProviders = $this->providerFactory->getAvailableProviders();
        $fallbackProviders = array_filter($availableProviders, fn($p) => $p !== $currentProvider);

        if (empty($fallbackProviders)) {
            $certificate->update(['status' => 'failed']);
            return [
                'success' => false,
                'error' => 'No fallback providers available',
                'certificate' => $certificate
            ];
        }

        foreach ($fallbackProviders as $providerName) {
            try {
                $provider = $this->providerFactory->getProvider($providerName);
                
                Log::info('Attempting fallback provider', [
                    'certificate_id' => $certificate->id,
                    'domain' => $domain,
                    'primary_provider' => $currentProvider,
                    'fallback_provider' => $providerName
                ]);

                $providerResult = $provider->createCertificate([$domain], [
                    'certificate_type' => $requirements['certificate_type'],
                    'contact_email' => $certificate->subscription->user->email,
                    'test_mode' => config('ssl.development.test_mode', false)
                ]);

                if ($providerResult['success']) {
                    // Update certificate with new provider
                    $certificate->update([
                        'provider' => $provider->getProviderName(),
                        'provider_certificate_id' => $providerResult['certificate_id'],
                        'provider_data' => $providerResult['provider_data'] ?? [],
                        'status' => $providerResult['status'] ?? 'pending_validation'
                    ]);

                    $this->scheduleValidationMonitoring($certificate, $provider);

                    Log::info('Fallback provider succeeded', [
                        'certificate_id' => $certificate->id,
                        'domain' => $domain,
                        'fallback_provider' => $providerName
                    ]);

                    return [
                        'success' => true,
                        'certificate' => $certificate,
                        'provider' => $provider->getProviderName(),
                        'provider_result' => $providerResult,
                        'fallback_used' => true
                    ];
                }

            } catch (\Exception $e) {
                Log::warning('Fallback provider failed', [
                    'certificate_id' => $certificate->id,
                    'domain' => $domain,
                    'fallback_provider' => $providerName,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // All fallback providers failed
        $certificate->update(['status' => 'failed']);
        
        return [
            'success' => false,
            'error' => 'All providers failed to issue certificate',
            'certificate' => $certificate
        ];
    }

    /**
     * Schedule validation monitoring for certificate
     */
    private function scheduleValidationMonitoring(Certificate $certificate, CertificateProviderInterface $provider): void
    {
        // スケジュール処理をジョブキューで実行
        ProcessCertificateValidation::dispatch($certificate)
            ->delay(now()->addMinutes(2));
    }

    /**
     * Get certificate status from provider
     */
    public function getCertificateStatus(Certificate $certificate): array
    {
        try {
            $provider = $this->providerFactory->getProvider($certificate->provider);
            $status = $provider->getCertificateStatus($certificate->provider_certificate_id);

            // Update local certificate status
            $this->updateCertificateFromProviderStatus($certificate, $status);

            return $status;

        } catch (\Exception $e) {
            Log::error('Failed to get certificate status', [
                'certificate_id' => $certificate->id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificate->provider_certificate_id,
                'provider' => $certificate->provider,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get validation instructions for certificate
     */
    public function getValidationInstructions(Certificate $certificate): array
    {
        try {
            $provider = $this->providerFactory->getProvider($certificate->provider);
            return $provider->getValidationInstructions($certificate->provider_certificate_id);

        } catch (\Exception $e) {
            Log::error('Failed to get validation instructions', [
                'certificate_id' => $certificate->id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificate->provider_certificate_id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage(),
                'instructions' => []
            ];
        }
    }

    /**
     * Download certificate files
     */
    public function downloadCertificate(Certificate $certificate): array
    {
        try {
            $provider = $this->providerFactory->getProvider($certificate->provider);
            return $provider->downloadCertificate($certificate->provider_certificate_id);

        } catch (\Exception $e) {
            Log::error('Failed to download certificate', [
                'certificate_id' => $certificate->id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Certificate download failed: ' . $e->getMessage());
        }
    }

    /**
     * Revoke certificate
     */
    public function revokeCertificate(Certificate $certificate, string $reason = 'subscription_cancelled'): array
    {
        try {
            $provider = $this->providerFactory->getProvider($certificate->provider);
            $result = $provider->revokeCertificate($certificate->provider_certificate_id, $reason);

            if ($result['success']) {
                $certificate->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revocation_reason' => $reason
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to revoke certificate', [
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
     * Update certificate status from provider response
     */
    private function updateCertificateFromProviderStatus(Certificate $certificate, array $providerStatus): void
    {
        $updates = [
            'status' => $providerStatus['status'] ?? $certificate->status,
            'provider_data' => array_merge(
                $certificate->provider_data ?? [],
                $providerStatus['provider_data'] ?? []
            )
        ];

        // Update issued/expires dates if available
        if (isset($providerStatus['issued_at'])) {
            $updates['issued_at'] = $providerStatus['issued_at'];
        }

        if (isset($providerStatus['expires_at'])) {
            $updates['expires_at'] = $providerStatus['expires_at'];
        }

        // Store certificate data if issued
        if ($providerStatus['is_issued'] ?? false) {
            $updates['status'] = 'issued';
            
            try {
                // Try to download certificate data
                $certData = $this->downloadCertificate($certificate);
                $updates['certificate_data'] = $certData;
            } catch (\Exception $e) {
                // Some providers (like Google Certificate Manager) don't support download
                Log::info('Certificate download not available for provider', [
                    'certificate_id' => $certificate->id,
                    'provider' => $certificate->provider
                ]);
            }
        }

        $certificate->update($updates);
    }

    /**
     * Get provider health status
     */
    public function getProviderHealthStatus(): array
    {
        return $this->providerFactory->getProviderHealthStatus();
    }

    /**
     * Test all providers
     */
    public function testAllProviders(): array
    {
        return $this->providerFactory->testAllProviders();
    }

    /**
     * Get provider comparison
     */
    public function getProviderComparison(): array
    {
        return $this->providerFactory->getProviderComparison();
    }

    /**
     * Get provider recommendations
     */
    public function getProviderRecommendations(): array
    {
        return $this->providerFactory->getProviderRecommendations();
    }

    /**
     * Validate domains across all providers
     */
    public function validateDomainsAcrossProviders(array $domains): array
    {
        return $this->providerFactory->validateDomainsAcrossProviders($domains);
    }

    /**
     * Check if subscription requires certificate download
     */
    private function requiresCertificateDownload(Subscription $subscription): bool
    {
        // Basic plans might need certificate downloads for manual installation
        return in_array($subscription->plan_type, ['basic']);
    }

    /**
     * Check if subscription prefers auto management
     */
    private function prefersAutoManagement(Subscription $subscription): bool
    {
        // Enterprise plans prefer automatic management
        return in_array($subscription->plan_type, ['enterprise', 'professional']);
    }

    /**
     * Get budget constraint for plan
     */
    private function getBudgetConstraint(string $planType): string
    {
        return match ($planType) {
            'basic' => 'low',
            'professional' => 'medium',
            'enterprise' => 'high',
            default => 'medium'
        };
    }

    /**
     * Create or get Square customer (existing method)
     */
    private function createOrGetSquareCustomer(User $user): array
    {
        try {
            // Search for existing customer
            $searchRequest = new SearchCustomersRequest([
                'filter' => [
                    'emailAddress' => [
                        'exact' => $user->email
                    ]
                ]
            ]);

            /** @var \Square\Customers\Responses\SearchCustomersResponse */
            $searchResponse = $this->squareClient->customers->search($searchRequest);

            if ($searchResponse->isSuccess() && !empty($searchResponse->getCustomers())) {
                $customer = $searchResponse->getCustomers()[0];
                return [
                    'id' => $customer->getId(),
                    'existing' => true
                ];
            }

            // Create new customer
            $address = new Address([
                'addressLine1' => $user->address ?? '',
                'locality' => $user->city ?? '',
                'administrativeDistrictLevel1' => $user->state ?? '',
                'postalCode' => $user->postal_code ?? '',
                'country' => $user->country ?? 'US'
            ]);

            /** @var CreateCustomerRequest */
            $createRequest = new CreateCustomerRequest([
                'givenName' => $user->first_name ?? $user->name ?? 'SSL SaaS User',
                'familyName' => $user->last_name ?? '',
                'emailAddress' => $user->email,
                'phoneNumber' => $user->phone ?? '',
                'address' => $address,
                'note' => 'SSL SaaS Platform Customer'
            ]);

            /** @var \Square\Customers\Responses\CreateCustomerResponse */
            $createResponse = $this->squareClient->customers->create($createRequest);

            if (!$createResponse->isSuccess()) {
                throw new \Exception('Failed to create customer: ' . json_encode($createResponse->getErrors()));
            }

            $customer = $createResponse->getCustomer();
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
     * Create local subscription record (existing method)
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
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Handle payment failure (existing method)
     */
    public function handlePaymentFailure(Subscription $subscription, array $webhookData): void
    {
        $failureCount = $subscription->payment_failed_attempts + 1;

        $subscription->update([
            'payment_failed_attempts' => $failureCount,
            'last_payment_failure' => now(),
            'status' => $failureCount >= 3 ? 'suspended' : 'past_due'
        ]);

        NotifyPaymentFailed::dispatch($subscription, $failureCount);

        if ($failureCount >= 3) {
            $this->suspendSubscriptionServices($subscription);
        }

        Log::warning('Payment failure handled', [
            'subscription_id' => $subscription->id,
            'failure_count' => $failureCount,
            'status' => $subscription->status
        ]);
    }

    /**
     * Handle successful payment (existing method)
     */
    public function handlePaymentSuccess(Subscription $subscription, array $webhookData): void
    {
        $subscription->update([
            'status' => 'active',
            'payment_failed_attempts' => 0,
            'last_payment_date' => now(),
            'last_payment_failure' => null,
            'next_billing_date' => $this->calculateNextBillingDate($subscription)
        ]);

        SendPaymentConfirmation::dispatch($subscription, $webhookData);
        $this->reactivateSubscriptionServices($subscription);

        Log::info('Payment success handled', [
            'subscription_id' => $subscription->id,
            'amount' => $webhookData['amount'] ?? 'unknown'
        ]);
    }

    /**
     * Cancel subscription with provider-specific cleanup
     */
    public function cancelSubscription(Subscription $subscription): array
    {
        try {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);

            // Revoke all active certificates with their respective providers
            $activeCertificates = $subscription->certificates()
                ->where('status', 'issued')
                ->get();

            $revocationResults = [];
            $activeCertificates->each(function ($certificate) use (&$revocationResults) {
                try {
                    $result = $this->revokeCertificate($certificate);
                    $revocationResults[] = [
                        'certificate_id' => $certificate->id,
                        'domain' => $certificate->domain,
                        'provider' => $certificate->provider,
                        'revoked' => $result['success']
                    ];
                } catch (\Exception $e) {
                    $revocationResults[] = [
                        'certificate_id' => $certificate->id,
                        'domain' => $certificate->domain,
                        'provider' => $certificate->provider,
                        'revoked' => false,
                        'error' => $e->getMessage()
                    ];
                }
            });

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'certificates_processed' => count($revocationResults),
                'revocation_results' => $revocationResults
            ]);

            return [
                'success' => true,
                'message' => 'Subscription cancelled successfully',
                'certificates_revoked' => count(array_filter($revocationResults, fn($r) => $r['revoked'])),
                'revocation_details' => $revocationResults
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to cancel subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Suspend subscription services
     */
    private function suspendSubscriptionServices(Subscription $subscription): void
    {
        $subscription->certificates()
            ->where('status', 'pending_validation')
            ->update(['status' => 'suspended']);
    }

    /**
     * Reactivate subscription services
     */
    private function reactivateSubscriptionServices(Subscription $subscription): void
    {
        $subscription->certificates()
            ->where('status', 'suspended')
            ->update(['status' => 'pending_validation']);
    }

    /**
     * Calculate next billing date
     */
    private function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        return match ($subscription->billing_period) {
            'MONTHLY' => now()->addMonth(),
            'QUARTERLY' => now()->addMonths(3),
            'ANNUALLY' => now()->addYear(),
            default => now()->addMonth()
        };
    }

    /**
     * Get dashboard data with provider information
     */
    public function getDashboardData(User $user): array
    {
        $subscription = $user->activeSubscription;
        
        if (!$subscription) {
            return [
                'has_subscription' => false,
                'plans' => $this->getAvailablePlans(),
                'provider_status' => $this->getProviderHealthStatus()
            ];
        }

        $certificates = $subscription->certificates()->get();
        
        // Group certificates by provider
        $certificatesByProvider = $certificates->groupBy('provider');
        
        $stats = [
            'total_certificates' => $certificates->count(),
            'active_certificates' => $certificates->where('status', 'issued')->count(),
            'pending_certificates' => $certificates->where('status', 'pending_validation')->count(),
            'expiring_soon' => $certificates->where('expires_at', '<=', now()->addDays(30))->count(),
            'domains_used' => $certificates->count(),
            'domains_limit' => $subscription->max_domains,
            'providers_used' => $certificatesByProvider->keys()->toArray(),
            'provider_distribution' => $certificatesByProvider->map->count()->toArray()
        ];

        return [
            'has_subscription' => true,
            'subscription' => $subscription,
            'certificates' => $certificates,
            'certificates_by_provider' => $certificatesByProvider,
            'stats' => $stats,
            'provider_status' => $this->getProviderHealthStatus(),
            'provider_recommendations' => $this->getProviderRecommendations()
        ];
    }

    /**
     * Get available plans
     */
    private function getAvailablePlans(): array
    {
        return [
            'basic' => [
                'name' => 'Basic SSL',
                'price' => '$9.99/month',
                'max_domains' => 1,
                'certificate_type' => 'Domain Validated',
                'provider' => 'GoGetSSL',
                'features' => [
                    '1 SSL Certificate',
                    'Domain Validation',
                    'Certificate Download',
                    '99.9% Uptime SLA'
                ]
            ],
            'professional' => [
                'name' => 'Professional SSL',
                'price' => '$29.99/month',
                'max_domains' => 5,
                'certificate_type' => 'Domain Validated',
                'provider' => 'Auto-Selected',
                'features' => [
                    'Up to 5 SSL Certificates',
                    'Auto Provider Selection',
                    'Priority Support',
                    '99.9% Uptime SLA'
                ]
            ],
            'enterprise' => [
                'name' => 'Enterprise SSL',
                'price' => '$99.99/month',
                'max_domains' => 100,
                'certificate_type' => 'Domain Validated',
                'provider' => 'Google Certificate Manager',
                'features' => [
                    'Up to 100 SSL Certificates',
                    'Automatic Management',
                    'Cloud Integration',
                    'Dedicated Support',
                    '99.9% Uptime SLA'
                ]
            ]
        ];
    }
}
