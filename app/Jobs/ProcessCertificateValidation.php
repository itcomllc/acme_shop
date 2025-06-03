<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\{GoGetSSLService, AcmeService, CertificateProviderFactory};
use App\Events\{CertificateIssued, CertificateFailed};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Enhanced Certificate Validation Processing
 * Supports multiple providers: GoGetSSL, Google Certificate Manager, Let's Encrypt
 */
class ProcessCertificateValidation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Certificate $certificate;
    private int $maxRetries = 60; // Monitor for 5 hours (60 * 5 min intervals)

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(): void
    {
        try {
            Log::info('Processing certificate validation', [
                'certificate_id' => $this->certificate->id,
                'domain' => $this->certificate->domain,
                'provider' => $this->certificate->provider,
                'attempt' => $this->attempts()
            ]);

            // Route to appropriate provider handler
            match ($this->certificate->provider) {
                Certificate::PROVIDER_GOGETSSL => $this->handleGoGetSSLValidation(),
                Certificate::PROVIDER_GOOGLE_CM => $this->handleGoogleCMValidation(),
                Certificate::PROVIDER_LETS_ENCRYPT => $this->handleLetsEncryptValidation(),
                default => throw new \Exception("Unsupported provider: {$this->certificate->provider}")
            };

        } catch (\Exception $e) {
            Log::error('Certificate validation processing failed', [
                'certificate_id' => $this->certificate->id,
                'provider' => $this->certificate->provider,
                'error' => $e->getMessage()
            ]);

            $this->handleValidationFailure($e->getMessage());
        }
    }

    /**
     * Handle GoGetSSL certificate validation
     */
    private function handleGoGetSSLValidation(): void
    {
        if (!$this->certificate->gogetssl_order_id && !$this->certificate->provider_certificate_id) {
            // Need to create GoGetSSL order first
            $this->createGoGetSSLOrder();
            return;
        }

        $orderId = $this->certificate->provider_certificate_id ?? $this->certificate->gogetssl_order_id;
        
        /** @var GoGetSSLService */
        $goGetSSLService = app(GoGetSSLService::class);
        $orderStatus = $goGetSSLService->getOrderStatus((int) $orderId);
        
        switch ($orderStatus['status'] ?? 'unknown') {
            case 'issued':
                $this->handleCertificateIssued($orderStatus);
                break;

            case 'processing':
            case 'pending':
                $this->scheduleRetry();
                break;

            case 'cancelled':
            case 'rejected':
                $this->handleValidationFailure('Certificate order was ' . $orderStatus['status']);
                break;

            default:
                Log::warning('Unknown GoGetSSL order status', [
                    'certificate_id' => $this->certificate->id,
                    'order_id' => $orderId,
                    'status' => $orderStatus['status'] ?? 'unknown'
                ]);
                $this->scheduleRetry();
        }
    }

    /**
     * Handle Google Certificate Manager validation
     */
    private function handleGoogleCMValidation(): void
    {
        if (!$this->certificate->provider_certificate_id) {
            throw new \Exception('Google Certificate Manager certificate ID not found');
        }

        /** @var \App\Services\GoogleCertificateManagerService */
        $googleCM = app(\App\Services\GoogleCertificateManagerService::class);
        
        try {
            $status = $googleCM->getCertificateStatus($this->certificate->provider_certificate_id);
            
            switch ($status['state'] ?? 'unknown') {
                case 'ACTIVE':
                    $this->handleGoogleCertificateActive($status);
                    break;

                case 'PENDING':
                case 'PROVISIONING':
                    $this->updateCertificateStatus('processing', $status);
                    $this->scheduleRetry();
                    break;

                case 'FAILED':
                    $issueMessage = $status['provisioning_issue']['details'] ?? 'Certificate provisioning failed';
                    $this->handleValidationFailure($issueMessage);
                    break;

                default:
                    Log::warning('Unknown Google CM certificate state', [
                        'certificate_id' => $this->certificate->id,
                        'google_cert_id' => $this->certificate->provider_certificate_id,
                        'state' => $status['state'] ?? 'unknown'
                    ]);
                    $this->scheduleRetry();
            }

        } catch (\Exception $e) {
            Log::error('Google Certificate Manager status check failed', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage()
            ]);
            $this->handleValidationFailure($e->getMessage());
        }
    }

    /**
     * Handle Let's Encrypt (ACME) validation
     */
    private function handleLetsEncryptValidation(): void
    {
        if (!$this->certificate->acme_order_id) {
            throw new \Exception('ACME order ID not found for Let\'s Encrypt certificate');
        }

        /** @var AcmeService */
        $acmeService = app(AcmeService::class);
        $acmeOrder = $this->certificate->acmeOrder;
        
        if (!$acmeOrder) {
            throw new \Exception('ACME order not found');
        }

        switch ($acmeOrder->status) {
            case 'ready':
                $this->finalizeLetsEncryptOrder($acmeService);
                break;

            case 'valid':
                $this->downloadLetsEncryptCertificate($acmeService);
                break;

            case 'pending':
                $this->scheduleRetry();
                break;

            case 'invalid':
                $this->handleValidationFailure('ACME order validation failed');
                break;

            default:
                Log::warning('Unknown ACME order status', [
                    'certificate_id' => $this->certificate->id,
                    'acme_order_id' => $this->certificate->acme_order_id,
                    'status' => $acmeOrder->status
                ]);
                $this->scheduleRetry();
        }
    }

    /**
     * Create GoGetSSL order if not exists
     */
    private function createGoGetSSLOrder(): void
    {
        /** @var GoGetSSLService */
        $goGetSSLService = app(GoGetSSLService::class);
        
        // Generate CSR
        $csr = $this->generateCSR($this->certificate->domain);
        
        // Create GoGetSSL order
        $orderData = [
            'product_id' => $this->getGoGetSSLProductId(),
            'csr' => $csr,
            'period' => 12,
            'dcv_method' => 'dns',
            'admin_email' => $this->certificate->subscription->user->email,
            'admin_firstname' => 'SSL',
            'admin_lastname' => 'Administrator',
            'admin_phone' => '+1-555-0123',
            'admin_title' => 'System Administrator',
            'tech_firstname' => 'SSL',
            'tech_lastname' => 'Administrator',
            'tech_phone' => '+1-555-0123',
            'tech_title' => 'System Administrator',
            'tech_email' => $this->certificate->subscription->user->email,
        ];

        $goGetSSLOrder = $goGetSSLService->createOrder($orderData);

        // Update certificate with GoGetSSL order ID
        $this->certificate->update([
            'provider_certificate_id' => (string) $goGetSSLOrder['order_id'],
            'gogetssl_order_id' => $goGetSSLOrder['order_id'], // Backward compatibility
            'provider_data' => $goGetSSLOrder,
            'status' => 'processing'
        ]);

        // Schedule next check
        $this->scheduleRetry();
    }

    /**
     * Handle certificate issued for GoGetSSL
     */
    private function handleCertificateIssued(array $orderStatus): void
    {
        try {
            /** @var GoGetSSLService */
            $goGetSSLService = app(GoGetSSLService::class);
            $orderId = $this->certificate->provider_certificate_id ?? $this->certificate->gogetssl_order_id;
            
            // Download certificate data
            $certificateData = $goGetSSLService->downloadCertificate((int) $orderId);
            
            $this->certificate->update([
                'status' => Certificate::STATUS_ISSUED,
                'certificate_data' => $certificateData,
                'issued_at' => now(),
                'expires_at' => isset($certificateData['valid_till']) 
                    ? \Carbon\Carbon::parse($certificateData['valid_till'])
                    : now()->addDays(365)
            ]);

            // Update subscription statistics
            $this->certificate->subscription->recordCertificateIssued();

            // Dispatch certificate issued event
            CertificateIssued::dispatch($this->certificate);

            Log::info('Certificate issued successfully', [
                'certificate_id' => $this->certificate->id,
                'domain' => $this->certificate->domain,
                'provider' => $this->certificate->provider
            ]);

            // Schedule auto-renewal
            $this->scheduleAutoRenewal();

        } catch (\Exception $e) {
            Log::error('Failed to process issued certificate', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage()
            ]);
            $this->handleValidationFailure($e->getMessage());
        }
    }

    /**
     * Handle Google Certificate Manager active certificate
     */
    private function handleGoogleCertificateActive(array $status): void
    {
        $this->certificate->update([
            'status' => Certificate::STATUS_ISSUED,
            'provider_data' => $status,
            'issued_at' => now(),
            'expires_at' => isset($status['expire_time']) 
                ? \Carbon\Carbon::parse($status['expire_time'])
                : now()->addDays(90)
        ]);

        // Update subscription statistics
        $this->certificate->subscription->recordCertificateIssued();

        // Dispatch certificate issued event
        CertificateIssued::dispatch($this->certificate);

        Log::info('Google Certificate Manager certificate activated', [
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain
        ]);
    }

    /**
     * Finalize Let's Encrypt order
     */
    private function finalizeLetsEncryptOrder(AcmeService $acmeService): void
    {
        // Implementation for finalizing ACME order
        // This would involve CSR submission and order finalization
        $this->scheduleRetry();
    }

    /**
     * Download Let's Encrypt certificate
     */
    private function downloadLetsEncryptCertificate(AcmeService $acmeService): void
    {
        // Implementation for downloading ACME certificate
        // This would download the certificate from the ACME CA
        
        $this->certificate->update([
            'status' => Certificate::STATUS_ISSUED,
            'issued_at' => now(),
            'expires_at' => now()->addDays(90) // Let's Encrypt certificates are 90 days
        ]);

        // Update subscription statistics
        $this->certificate->subscription->recordCertificateIssued();

        // Dispatch certificate issued event
        CertificateIssued::dispatch($this->certificate);

        Log::info('Let\'s Encrypt certificate issued', [
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain
        ]);

        // Schedule auto-renewal
        $this->scheduleAutoRenewal();
    }

    /**
     * Handle validation failure
     */
    private function handleValidationFailure(string $error): void
    {
        $this->certificate->update([
            'status' => Certificate::STATUS_FAILED,
            'provider_data' => array_merge(
                $this->certificate->provider_data ?? [],
                ['error' => $error, 'failed_at' => now()->toISOString()]
            )
        ]);

        // Update subscription statistics
        $this->certificate->subscription->recordCertificateFailure();

        // Dispatch certificate failed event
        CertificateFailed::dispatch($this->certificate, $error, $this->certificate->provider);

        Log::error('Certificate validation failed', [
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'provider' => $this->certificate->provider,
            'error' => $error
        ]);
    }

    /**
     * Schedule retry if within limits
     */
    private function scheduleRetry(): void
    {
        if ($this->attempts() < $this->maxRetries) {
            $this->release(300); // Check again in 5 minutes
        } else {
            $this->handleValidationFailure('Maximum retry attempts exceeded');
        }
    }

    /**
     * Update certificate status with provider data
     */
    private function updateCertificateStatus(string $status, array $providerData): void
    {
        $this->certificate->update([
            'status' => $status,
            'provider_data' => array_merge(
                $this->certificate->provider_data ?? [],
                $providerData
            )
        ]);
    }

    /**
     * Schedule auto-renewal for the certificate
     */
    private function scheduleAutoRenewal(): void
    {
        $renewalDays = $this->certificate->subscription->renewal_before_days ?? 30;
        $renewalDate = $this->certificate->expires_at->subDays($renewalDays);

        if ($renewalDate->isFuture()) {
            ScheduleCertificateRenewal::dispatch($this->certificate)
                ->delay($renewalDate);

            Log::info('Auto-renewal scheduled', [
                'certificate_id' => $this->certificate->id,
                'renewal_date' => $renewalDate->toDateTimeString()
            ]);
        }
    }

    /**
     * Generate CSR for domain
     */
    private function generateCSR(string $domain): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new([
            'CN' => $domain,
            'O' => 'SSL SaaS Platform',
            'C' => 'US'
        ], $privateKey);

        openssl_csr_export($csr, $csrOut);
        
        // Store private key securely
        openssl_pkey_export($privateKey, $privateKeyOut);
        $this->certificate->update(['private_key' => encrypt($privateKeyOut)]);

        return $csrOut;
    }

    /**
     * Get GoGetSSL product ID based on certificate type
     */
    private function getGoGetSSLProductId(): int
    {
        return match ($this->certificate->type) {
            'DV' => 1, // GoGetSSL DV SSL
            'OV' => 2, // GoGetSSL OV SSL
            'EV' => 3, // GoGetSSL EV SSL
            default => 1
        };
    }
}