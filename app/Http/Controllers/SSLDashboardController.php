<?php

namespace App\Http\Controllers;

use App\Services\SSLSaaSService;
use App\Models\{User, Subscription, Certificate};
use Illuminate\Http\{Request, JsonResponse};
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SSL SaaS Dashboard Controller
 */
class SSLDashboardController extends Controller
{
    public function __construct(
        private readonly SSLSaaSService $sslService
    ) {}

    /**
     * Dashboard overview
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User */
        $user = $request->user();
        $subscription = $user->activeSubscription;
        
        if (!$subscription) {
            return response()->json([
                'has_subscription' => false,
                'plans' => $this->getAvailablePlans()
            ]);
        }

        $certificates = $subscription->certificates()
            ->with(['validationRecords'])
            ->get();

        $stats = [
            'total_certificates' => $certificates->count(),
            'active_certificates' => $certificates->where('status', 'issued')->count(),
            'pending_certificates' => $certificates->where('status', 'pending_validation')->count(),
            'expiring_soon' => $certificates->where('expires_at', '<=', now()->addDays(30))->count(),
            'domains_used' => $certificates->count(),
            'domains_limit' => $subscription->max_domains
        ];

        return response()->json([
            'has_subscription' => true,
            'subscription' => $subscription,
            'certificates' => $certificates,
            'stats' => $stats
        ]);
    }

    /**
     * Create new subscription
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'plan_type' => 'required|in:basic,professional,enterprise',
            'domains' => 'required|array|min:1|max:100',
            'domains.*' => 'required|string|regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
            'card_nonce' => 'required|string' // 追加
        ]);

        try {
            /** @var User */
            $user = $request->user();
            
            $result = $this->sslService->createSubscription(
                $user,
                $request->plan_type,
                $request->domains,
                $request->card_nonce // 4番目の引数として追加
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'subscription' => $result['subscription'],
                    'customer_id' => $result['customer_id']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Issue new certificate for existing subscription
     */
    public function issueCertificate(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/'
        ]);

        /** @var User */
        $user = $request->user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => 'No active subscription found'
            ], 400);
        }

        if ($subscription->certificates()->count() >= $subscription->max_domains) {
            return response()->json([
                'success' => false,
                'error' => 'Domain limit reached for current plan'
            ], 400);
        }

        try {
            $certificate = $this->sslService->issueCertificate(
                $subscription,
                $request->domain
            );

            return response()->json([
                'success' => true,
                'certificate' => $certificate
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get certificate validation instructions
     */
    public function getCertificateValidation(Request $request, Certificate $certificate): JsonResponse
    {
        /** @var User */
        $user = $request->user();
        
        if ($certificate->subscription->user_id !== $user->id) {
            abort(403);
        }

        $validationRecords = $certificate->validationRecords;
        $instructions = [];

        foreach ($validationRecords as $record) {
            match ($record->type) {
                'http-01' => $instructions[] = [
                    'type' => 'HTTP',
                    'description' => 'Place the following file on your web server',
                    'file_path' => "/.well-known/acme-challenge/{$record->token}",
                    'file_content' => $record->key_authorization,
                    'verification_url' => "http://{$certificate->domain}/.well-known/acme-challenge/{$record->token}"
                ],
                'dns-01' => $instructions[] = [
                    'type' => 'DNS',
                    'description' => 'Add the following DNS TXT record',
                    'record_name' => "_acme-challenge.{$certificate->domain}",
                    'record_value' => base64url_encode(hash('sha256', $record->key_authorization, true)),
                    'ttl' => 300
                ],
                default => null
            };
        }

        return response()->json([
            'certificate' => $certificate,
            'validation_instructions' => $instructions,
            'status' => $certificate->status
        ]);
    }

    /**
     * Download certificate files
     */
    public function downloadCertificate(Request $request, Certificate $certificate): StreamedResponse
    {
        /** @var User */
        $user = $request->user();
        
        if ($certificate->subscription->user_id !== $user->id) {
            abort(403);
        }

        if ($certificate->status !== 'issued') {
            abort(404, 'Certificate not ready for download');
        }

        $certificateData = $certificate->certificate_data;
        
        return response()->streamDownload(function () use ($certificateData) {
            echo $certificateData['certificate'];
        }, "certificate_{$certificate->domain}.pem", [
            'Content-Type' => 'application/x-pem-file'
        ]);
    }

    /**
     * Get available subscription plans
     * @return array<string, array<string, mixed>>
     */
    private function getAvailablePlans(): array
    {
        return [
            'basic' => [
                'name' => 'Basic SSL',
                'price' => 999, // セント単位で統一
                'max_domains' => 1, // SSLSaaSServiceと統一
                'certificate_type' => 'DV',
                'features' => [
                    '1 SSL Certificate',
                    'Domain Validation',
                    'ACME Automation',
                    '99.9% Uptime SLA'
                ]
            ],
            'professional' => [
                'name' => 'Professional SSL',
                'price' => 2999,
                'max_domains' => 5,
                'certificate_type' => 'OV',
                'features' => [
                    'Up to 5 SSL Certificates',
                    'Organization Validation',
                    'ACME Automation',
                    'Priority Support',
                    '99.9% Uptime SLA'
                ]
            ],
            'enterprise' => [
                'name' => 'Enterprise SSL',
                'price' => 9999,
                'max_domains' => 100,
                'certificate_type' => 'EV',
                'features' => [
                    'Up to 100 SSL Certificates',
                    'Extended Validation',
                    'ACME Automation',
                    'Dedicated Support',
                    '99.9% Uptime SLA',
                    'Custom Integrations'
                ]
            ]
        ];
    }
}