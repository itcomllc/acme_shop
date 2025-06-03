<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Subscription, Certificate};
use App\Services\EnhancedSSLSaaSService;
use App\Http\Resources\{SubscriptionResource, CertificateResource};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Log, Auth, DB};

/**
 * SSL Subscription API Controller
 * 
 * Handles subscription management via API endpoints
 */
class SSLSubscriptionController extends Controller
{
    public function __construct(
        private readonly EnhancedSSLSaaSService $sslService
    ) {}

    /**
     * Get all subscriptions for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Subscription::where('user_id', $user->id);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('plan_type')) {
                $query->where('plan_type', $request->plan_type);
            }

            // Pagination
            $perPage = min((int) ($request->per_page ?: 15), 100);
            $subscriptions = $query->with(['certificates'])
                                  ->orderBy('created_at', 'desc')
                                  ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => SubscriptionResource::collection($subscriptions),
                'meta' => [
                    'total' => $subscriptions->total(),
                    'per_page' => $subscriptions->perPage(),
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch subscriptions', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific subscription details
     */
    public function show(Subscription $subscription): JsonResponse
    {
        try {
            // Check ownership
            if ($subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            $subscription->load(['certificates', 'payments']);

            return response()->json([
                'success' => true,
                'data' => new SubscriptionResource($subscription)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch subscription details', [
                'subscription_id' => $subscription->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new subscription
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'plan_type' => 'required|in:basic,professional,enterprise',
            'domains' => 'required|array|min:1|max:100',
            'domains.*' => 'required|string|ssl_domain',
            'card_nonce' => 'required|string'
        ]);

        try {
            $user = Auth::user();
            
            // Check if user already has an active subscription
            if ($user->activeSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has an active subscription'
                ], 422);
            }

            $result = $this->sslService->createSubscription(
                $user,
                $request->plan_type,
                $request->domains,
                $request->card_nonce
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription created successfully',
                    'data' => [
                        'subscription' => new SubscriptionResource($result['subscription']),
                        'certificates' => CertificateResource::collection($result['certificates'] ?? []),
                        'customer_id' => $result['customer_id']
                    ]
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create subscription',
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to create subscription', [
                'user_id' => Auth::id(),
                'plan_type' => $request->plan_type,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add domain to subscription
     */
    public function addDomain(Subscription $subscription, Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|ssl_domain',
            'provider' => 'nullable|string|ssl_provider'
        ]);

        try {
            // Check ownership
            if ($subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Check domain limits
            if (!$subscription->canAddDomain()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain limit exceeded for this subscription'
                ], 422);
            }

            // Check if domain already exists
            $existingCertificate = $subscription->certificates()
                                               ->where('domain', $request->domain)
                                               ->exists();

            if ($existingCertificate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain already exists in this subscription'
                ], 422);
            }

            // Issue certificate for the new domain
            $certificate = $this->sslService->issueCertificateWithProvider(
                $subscription,
                $request->domain,
                $request->provider
            );

            return response()->json([
                'success' => true,
                'message' => 'Domain added successfully',
                'data' => new CertificateResource($certificate)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to add domain to subscription', [
                'subscription_id' => $subscription->id,
                'domain' => $request->domain,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add domain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove domain from subscription
     */
    public function removeDomain(Subscription $subscription, string $domain): JsonResponse
    {
        try {
            // Check ownership
            if ($subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Find certificate for the domain
            $certificate = $subscription->certificates()
                                       ->where('domain', $domain)
                                       ->first();

            if (!$certificate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found in this subscription'
                ], 404);
            }

            DB::transaction(function () use ($certificate) {
                // Revoke certificate if it's active
                if ($certificate->status === Certificate::STATUS_ISSUED) {
                    $this->sslService->revokeCertificate($certificate, 'domain_removed');
                }

                // Mark certificate as removed (don't delete for audit trail)
                $certificate->update([
                    'status' => 'removed',
                    'revoked_at' => now(),
                    'revocation_reason' => 'domain_removed'
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Domain removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove domain from subscription', [
                'subscription_id' => $subscription->id,
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove domain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change default provider for subscription
     */
    public function changeProvider(Subscription $subscription, Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|ssl_provider'
        ]);

        try {
            // Check ownership
            if ($subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            $oldProvider = $subscription->default_provider;
            
            $subscription->update([
                'default_provider' => $request->provider
            ]);

            Log::info('Subscription provider changed', [
                'subscription_id' => $subscription->id,
                'old_provider' => $oldProvider,
                'new_provider' => $request->provider
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Provider changed successfully',
                'data' => new SubscriptionResource($subscription->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to change subscription provider', [
                'subscription_id' => $subscription->id,
                'provider' => $request->provider,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change provider',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription statistics
     */
    public function statistics(Subscription $subscription): JsonResponse
    {
        try {
            // Check ownership
            if ($subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            $stats = $subscription->getStatistics();
            
            // Add provider-specific statistics
            $providerStats = [];
            $certificates = $subscription->certificates();
            
            foreach (['gogetssl', 'google_certificate_manager', 'lets_encrypt'] as $provider) {
                $providerCerts = $certificates->where('provider', $provider);
                $providerStats[$provider] = [
                    'total' => $providerCerts->count(),
                    'active' => $providerCerts->where('status', Certificate::STATUS_ISSUED)->count(),
                    'pending' => $providerCerts->where('status', Certificate::STATUS_PENDING)->count(),
                    'failed' => $providerCerts->where('status', Certificate::STATUS_FAILED)->count(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription_stats' => $stats,
                    'provider_stats' => $providerStats,
                    'billing_info' => [
                        'next_billing_amount' => $subscription->getNextBillingAmount(),
                        'formatted_price' => $subscription->getFormattedPrice(),
                        'billing_period' => $subscription->getBillingPeriodDisplay(),
                        'next_billing_date' => $subscription->next_billing_date?->toISOString(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get subscription statistics', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pause subscription
     */
    public function pause(Subscription $subscription): JsonResponse
    {
        try {
            // Check ownership
            if ($subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            if ($subscription->status !== Subscription::STATUS_ACTIVE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active subscriptions can be paused'
                ], 422);
            }

            $subscription->update([
                'status' => Subscription::STATUS_PAUSED,
                'paused_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription paused successfully',
                'data' => new SubscriptionResource($subscription->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to pause subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to pause subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume subscription
     */
    public function resume(Subscription $subscription): JsonResponse
    {
        try {
            // Check ownership
            if ($subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            if ($subscription->status !== Subscription::STATUS_PAUSED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only paused subscriptions can be resumed'
                ], 422);
            }

            $subscription->update([
                'status' => Subscription::STATUS_ACTIVE,
                'resumed_at' => now(),
                'paused_at' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully',
                'data' => new SubscriptionResource($subscription->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to resume subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resume subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk migrate provider for subscriptions
     */
    public function bulkMigrateProvider(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_ids' => 'required|array|max:50',
            'subscription_ids.*' => 'integer|exists:subscriptions,id',
            'new_provider' => 'required|string|ssl_provider'
        ]);

        try {
            $user = Auth::user();
            $results = [];

            $subscriptions = Subscription::whereIn('id', $request->subscription_ids)
                                       ->where('user_id', $user->id)
                                       ->get();

            foreach ($subscriptions as $subscription) {
                try {
                    $oldProvider = $subscription->default_provider;
                    
                    $subscription->update([
                        'default_provider' => $request->new_provider
                    ]);

                    $results[] = [
                        'subscription_id' => $subscription->id,
                        'old_provider' => $oldProvider,
                        'new_provider' => $request->new_provider,
                        'status' => 'success'
                    ];

                } catch (\Exception $e) {
                    $results[] = [
                        'subscription_id' => $subscription->id,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk provider migration completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk subscription provider migration failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk migration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
