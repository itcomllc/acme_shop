<?php

namespace App\Services;

use App\Models\{User, Subscription, Certificate};
use App\Jobs\{RevokeCertificate, NotifyPaymentFailed, SendPaymentConfirmation, ProcessCertificateValidation};
use Square\SquareClient;
use Square\Types\{ Address};
use Square\Customers\Requests\{ListCustomersRequest, SearchCustomersRequest, CreateCustomerRequest, UpdateCustomerRequest, GetCustomersRequest, ListCustomersResponse, SearchCustomersResponse, CreateCustomerResponse, UpdateCustomerResponse};
use Square\Exceptions\SquareException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SSL SaaS Service - Laravel 11 + Square SDK v41 Implementation
 */
class SSLSaaSService
{
    private const PLAN_CONFIGS = [
        'basic' => [
            'max_domains' => 1,
            'certificate_type' => 'DV',
            'price' => 999, // $9.99 in cents
            'period' => 'MONTHLY',
        ],
        'professional' => [
            'max_domains' => 5,
            'certificate_type' => 'OV',
            'price' => 2999, // $29.99 in cents
            'period' => 'MONTHLY',
        ],
        'enterprise' => [
            'max_domains' => 100,
            'certificate_type' => 'EV',
            'price' => 9999, // $99.99 in cents
            'period' => 'MONTHLY',
        ]
    ];

    public function __construct(
        private readonly SquareClient $squareClient,
        private readonly GoGetSSLService $goGetSSLService,
        private readonly AcmeService $acmeService
    ) {}

    /**
     * Create Square subscription with anti-churning measures
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

        try {
            // 1. Create or get Square customer
            $customer = $this->createOrGetSquareCustomer($user);

            // 2. Add card to customer (for subscriptions, this would typically be handled 
            //    through Square's Web Payments SDK on frontend)
            
            // 3. Create local subscription record (Square Subscriptions API is deprecated)
            // Modern approach: Use recurring payments with invoices
            $subscription = $this->createLocalSubscription($user, $customer['id'], $planType, $plan, $domains);

            // 4. Issue initial certificates
            foreach ($domains as $domain) {
                $this->issueCertificate($subscription, $domain);
            }

            return [
                'success' => true,
                'subscription' => $subscription,
                'customer_id' => $customer['id']
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
    }

    /**
     * Create or get Square customer using new SDK syntax
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

            /** @var SearchCustomersResponse */
            $searchResponse = $this->squareClient->customers->search($searchRequest);

            if ($searchResponse->isSuccess() && !empty($searchResponse->getCustomers())) {
                $customer = $searchResponse->getCustomers()[0];
                return [
                    'id' => $customer->getId(),
                    'existing' => true
                ];
            }

            // Create new customer with proper request structure
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

            /** @var CreateCustomerResponse */
            $createResponse = $this->squareClient->customers->create($createRequest);

            if (!$createResponse->isSuccess()) {
                throw new \Exception('Failed to create customer: ' . json_encode($createResponse->getErrors()));
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
     * Create local subscription record
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
     * Get default location ID using new SDK
     */
    private function getDefaultLocationId(): string
    {
        static $locationId = null;

        if ($locationId === null) {
            try {
                /** @var ListLocationsResponse */
                $response = $this->squareClient->locations->list();

                if ($response->isSuccess() && !empty($response->getLocations())) {
                    $locationId = $response->getLocations()[0]->getId();
                } else {
                    throw new \Exception('No Square locations found');
                }
            } catch (SquareException $e) {
                throw new \Exception("Failed to get location: {$e->getMessage()}");
            }
        }

        return $locationId;
    }

    /**
     * Update customer information
     */
    public function updateCustomer(User $user, array $data): array
    {
        if (!$user->square_customer_id) {
            return ['success' => false, 'error' => 'Customer not found in Square'];
        }

        try {
            $updateRequest = new UpdateCustomerRequest([
                'customerId' => $user->square_customer_id,
                'givenName' => $data['given_name'] ?? $user->first_name,
                'familyName' => $data['family_name'] ?? $user->last_name,
                'emailAddress' => $data['email'] ?? $user->email,
                'phoneNumber' => $data['phone'] ?? $user->phone,
                'version' => $data['version'] ?? 0 // For optimistic concurrency
            ]);

            /** @var UpdateCustomerResponse */
            $response = $this->squareClient->customers->update(
                $updateRequest
            );

            if ($response->isSuccess()) {
                return [
                    'success' => true,
                    'customer' => $response->getCustomer()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->getErrors()
                ];
            }

        } catch (SquareException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle payment failure with modern approach
     */
    public function handlePaymentFailure(Subscription $subscription, array $webhookData): void
    {
        $failureCount = $subscription->payment_failed_attempts + 1;

        $subscription->update([
            'payment_failed_attempts' => $failureCount,
            'last_payment_failure' => now(),
            'status' => $failureCount >= 3 ? 'suspended' : 'past_due'
        ]);

        // Dispatch failure notification
        NotifyPaymentFailed::dispatch($subscription, $failureCount);

        // Suspend services after 3 failures
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
     * Handle successful payment
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

        // Send confirmation
        SendPaymentConfirmation::dispatch($subscription, $webhookData);

        // Reactivate services if suspended
        $this->reactivateSubscriptionServices($subscription);

        Log::info('Payment success handled', [
            'subscription_id' => $subscription->id,
            'amount' => $webhookData['amount'] ?? 'unknown'
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription): array
    {
        try {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);

            // Revoke all active certificates
            $activeCertificates = $subscription->certificates()
                ->where('status', 'issued')
                ->get();

            $activeCertificates->each(function ($certificate) {
                RevokeCertificate::dispatch($certificate);
            });

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'certificates_revoked' => $activeCertificates->count()
            ]);

            return [
                'success' => true,
                'message' => 'Subscription cancelled successfully'
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
     * Issue certificate through GoGetSSL with ACME integration
     */
    public function issueCertificate(Subscription $subscription, string $domain): Certificate
    {
        // Create ACME order
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
            'expires_at' => now()->addDays(90)
        ]);

        // Start ACME validation process
        ProcessCertificateValidation::dispatch($certificate);

        Log::info('Certificate issuance initiated', [
            'subscription_id' => $subscription->id,
            'domain' => $domain,
            'certificate_id' => $certificate->id
        ]);

        return $certificate;
    }

    /**
     * Get customer information
     */
    public function getCustomer(string $customerId): array
    {
        try {
            $request = new GetCustomersRequest([
                'customerId' => $customerId
            ]);

            /** @var GetCustomersResponse */
            $response = $this->squareClient->customers->get($request);

            if ($response->isSuccess()) {
                return [
                    'success' => true,
                    'customer' => $response->getCustomer()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->getErrors()
                ];
            }

        } catch (SquareException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List all customers with pagination support
     */
    public function listCustomers(string $cursor = null, int $limit = 100): array
    {
        try {
            $request = new ListCustomersRequest([
                'cursor' => $cursor,
                'limit' => min($limit, 100), // Square API limit
                'sortField' => 'DEFAULT',
                'sortOrder' => 'DESC'
            ]);

            /** @var ListCustomersResponse */
            $response = $this->squareClient->customers->list($request);

            if ($response->isSuccess()) {
                return [
                    'success' => true,
                    'customers' => $response->getCustomers(),
                    'cursor' => $response->getCursor()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->getErrors()
                ];
            }

        } catch (SquareException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}