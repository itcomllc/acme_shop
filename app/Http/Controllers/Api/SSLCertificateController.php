<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Certificate, Subscription};
use App\Services\EnhancedSSLSaaSService;
use App\Jobs\{ScheduleCertificateRenewal, RevokeCertificate, ProcessCertificateValidation};
use App\Http\Requests\{CreateCertificateRequest, RenewCertificateRequest, RevokeCertificateRequest};
use App\Http\Resources\{CertificateResource, CertificateCollection};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Log, Auth};

/**
 * SSL Certificate API Controller
 * 
 * Handles certificate management via API endpoints
 */
class SSLCertificateController extends Controller
{
    public function __construct(
        private readonly EnhancedSSLSaaSService $sslService
    ) {}

    /**
     * Get all certificates for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Certificate::whereHas('subscription', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('provider')) {
                $query->where('provider', $request->provider);
            }

            if ($request->has('domain')) {
                $query->where('domain', 'like', '%' . $request->domain . '%');
            }

            if ($request->has('expiring')) {
                $days = (int) ($request->expiring ?: 30);
                $query->expiring($days);
            }

            // Pagination
            $perPage = min((int) ($request->per_page ?: 15), 100);
            $certificates = $query->with(['subscription', 'acmeOrder'])
                                 ->orderBy('created_at', 'desc')
                                 ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => new CertificateCollection($certificates),
                'meta' => [
                    'total' => $certificates->total(),
                    'per_page' => $certificates->perPage(),
                    'current_page' => $certificates->currentPage(),
                    'last_page' => $certificates->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch certificates', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch certificates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific certificate details
     */
    public function show(Certificate $certificate): JsonResponse
    {
        try {
            // Check ownership
            if ($certificate->subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate not found'
                ], 404);
            }

            $certificate->load(['subscription', 'acmeOrder', 'validationRecords', 'renewals']);

            return response()->json([
                'success' => true,
                'data' => new CertificateResource($certificate)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch certificate details', [
                'certificate_id' => $certificate->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch certificate details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new certificate
     */
    public function store(CreateCertificateRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $subscription = Subscription::where('user_id', $user->id)
                                      ->where('id', $request->subscription_id)
                                      ->firstOrFail();

            // Check domain limits
            if (!$subscription->canAddDomain()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain limit exceeded for this subscription'
                ], 422);
            }

            // Issue certificate using the enhanced service
            $certificate = $this->sslService->issueCertificateWithProvider(
                $subscription,
                $request->domain,
                $request->provider
            );

            return response()->json([
                'success' => true,
                'message' => 'Certificate creation initiated',
                'data' => new CertificateResource($certificate)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create certificate', [
                'user_id' => Auth::id(),
                'domain' => $request->domain,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew certificate
     */
    public function renew(Certificate $certificate, RenewCertificateRequest $request): JsonResponse
    {
        try {
            // Check ownership
            if ($certificate->subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate not found'
                ], 404);
            }

            // Check if renewal is needed
            if (!$certificate->isExpiringSoon($request->force ? 0 : 30)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate does not need renewal yet'
                ], 422);
            }

            $renewalResult = $this->sslService->renewCertificate($certificate);

            return response()->json([
                'success' => true,
                'message' => 'Certificate renewal initiated',
                'data' => [
                    'old_certificate' => new CertificateResource($renewalResult['old_certificate']),
                    'new_certificate' => new CertificateResource($renewalResult['new_certificate']),
                    'provider' => $renewalResult['provider']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to renew certificate', [
                'certificate_id' => $certificate->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to renew certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke certificate
     */
    public function revoke(Certificate $certificate, RevokeCertificateRequest $request): JsonResponse
    {
        try {
            // Check ownership
            if ($certificate->subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate not found'
                ], 404);
            }

            // Check if certificate can be revoked
            if ($certificate->isRevoked()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate is already revoked'
                ], 422);
            }

            // Queue revocation job
            RevokeCertificate::dispatch($certificate, $request->reason);

            // Update certificate status immediately
            $certificate->update([
                'status' => Certificate::STATUS_REVOKED,
                'revoked_at' => now(),
                'revocation_reason' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Certificate revocation initiated',
                'data' => new CertificateResource($certificate->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revoke certificate', [
                'certificate_id' => $certificate->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download certificate files
     */
    public function download(Certificate $certificate, Request $request): JsonResponse
    {
        try {
            // Check ownership
            if ($certificate->subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate not found'
                ], 404);
            }

            // Check if certificate is issued
            if ($certificate->status !== Certificate::STATUS_ISSUED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate is not yet issued'
                ], 422);
            }

            $format = $request->get('format', 'pem'); // pem, p7b, p12
            $includePrivateKey = $request->boolean('include_private_key');

            // Get certificate data from provider
            $certificateFiles = $this->getCertificateFiles($certificate, $format, $includePrivateKey);

            return response()->json([
                'success' => true,
                'data' => $certificateFiles
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to download certificate', [
                'certificate_id' => $certificate->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get certificate validation status
     */
    public function validationStatus(Certificate $certificate): JsonResponse
    {
        try {
            // Check ownership
            if ($certificate->subscription->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate not found'
                ], 404);
            }

            $validationRecords = $certificate->validationRecords()
                                           ->orderBy('created_at', 'desc')
                                           ->limit(10)
                                           ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'certificate_id' => $certificate->id,
                    'domain' => $certificate->domain,
                    'status' => $certificate->status,
                    'provider' => $certificate->provider,
                    'validation_records' => $validationRecords,
                    'provider_data' => $certificate->provider_data,
                    'last_updated' => $certificate->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get validation status', [
                'certificate_id' => $certificate->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get validation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk renew certificates
     */
    public function bulkRenew(Request $request): JsonResponse
    {
        $request->validate([
            'certificate_ids' => 'required|array|max:50',
            'certificate_ids.*' => 'integer|exists:certificates,id',
            'force' => 'boolean'
        ]);

        try {
            $user = Auth::user();
            $results = [];

            $certificates = Certificate::whereIn('id', $request->certificate_ids)
                                     ->whereHas('subscription', function ($q) use ($user) {
                                         $q->where('user_id', $user->id);
                                     })
                                     ->get();

            foreach ($certificates as $certificate) {
                try {
                    if ($certificate->isExpiringSoon($request->force ? 0 : 30)) {
                        ScheduleCertificateRenewal::dispatch($certificate);
                        $results[] = [
                            'certificate_id' => $certificate->id,
                            'domain' => $certificate->domain,
                            'status' => 'renewal_scheduled'
                        ];
                    } else {
                        $results[] = [
                            'certificate_id' => $certificate->id,
                            'domain' => $certificate->domain,
                            'status' => 'not_eligible'
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'certificate_id' => $certificate->id,
                        'domain' => $certificate->domain,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk renewal initiated',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk certificate renewal failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk renewal failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GoGetSSL webhook handler
     */
    public function gogetSSLWebhook(Request $request): JsonResponse
    {
        try {
            Log::info('GoGetSSL webhook received', $request->all());

            $orderId = $request->get('order_id');
            $status = $request->get('status');

            if (!$orderId || !$status) {
                return response()->json(['success' => false, 'message' => 'Invalid webhook data'], 400);
            }

            $certificate = Certificate::where('provider_certificate_id', $orderId)
                                    ->orWhere('gogetssl_order_id', $orderId)
                                    ->first();

            if (!$certificate) {
                Log::warning('Certificate not found for GoGetSSL webhook', ['order_id' => $orderId]);
                return response()->json(['success' => false, 'message' => 'Certificate not found'], 404);
            }

            // Process webhook based on status
            match ($status) {
                'issued' => $this->handleCertificateIssued($certificate, $request->all()),
                'cancelled', 'rejected' => $this->handleCertificateFailed($certificate, $status),
                default => Log::info('Unhandled GoGetSSL webhook status', ['status' => $status])
            };

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('GoGetSSL webhook processing failed', [
                'error' => $e->getMessage(),
                'webhook_data' => $request->all()
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Get certificate files in requested format
     */
    private function getCertificateFiles(Certificate $certificate, string $format, bool $includePrivateKey): array
    {
        $files = [];
        $certificateData = $certificate->certificate_data;

        if (!$certificateData) {
            throw new \Exception('Certificate data not available');
        }

        // Add certificate content based on format
        switch ($format) {
            case 'pem':
                $files['certificate.crt'] = $certificateData['crt'] ?? '';
                $files['ca_bundle.crt'] = $certificateData['ca_bundle'] ?? '';
                break;
            case 'p7b':
                $files['certificate.p7b'] = $certificateData['p7b'] ?? '';
                break;
            case 'p12':
                $files['certificate.p12'] = $certificateData['p12'] ?? '';
                break;
        }

        // Add private key if requested and available
        if ($includePrivateKey && $certificate->private_key) {
            try {
                $files['private.key'] = decrypt($certificate->private_key);
            } catch (\Exception $e) {
                Log::warning('Failed to decrypt private key', [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $files;
    }

    /**
     * Handle certificate issued webhook
     */
    private function handleCertificateIssued(Certificate $certificate, array $webhookData): void
    {
        $certificate->update([
            'status' => Certificate::STATUS_ISSUED,
            'issued_at' => now(),
            'provider_data' => array_merge($certificate->provider_data ?? [], $webhookData)
        ]);

        // Trigger certificate processing to download files
        ProcessCertificateValidation::dispatch($certificate);
    }

    /**
     * Handle certificate failure webhook
     */
    private function handleCertificateFailed(Certificate $certificate, string $reason): void
    {
        $certificate->update([
            'status' => Certificate::STATUS_FAILED,
            'provider_data' => array_merge($certificate->provider_data ?? [], [
                'failure_reason' => $reason,
                'failed_at' => now()->toISOString()
            ])
        ]);
    }
}