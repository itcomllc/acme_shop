<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\{GoGetSSLService, AcmeService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Process certificate validation and issuance
 */
class ProcessCertificateValidation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Certificate $certificate;

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(GoGetSSLService $goGetSSLService, AcmeService $acmeService): void
    {
        // Wait for ACME validation to complete
        $acmeOrder = $this->certificate->acmeOrder;
        
        if ($acmeOrder->status !== 'ready') {
            // Re-queue job to check later
            $this->release(60); // Check again in 1 minute
            return;
        }

        // Generate CSR
        $csr = $this->generateCSR($this->certificate->domain);
        
        // Create GoGetSSL order
        $goGetSSLOrder = $goGetSSLService->createOrder([
            'product_id' => $this->getProductId($this->certificate->type),
            'csr' => $csr,
            'period' => 12,
            'dcv_method' => 'dns',
            'admin_email' => $this->certificate->subscription->user->email,
            // Add other required fields...
        ]);

        // Update certificate with GoGetSSL order ID
        $this->certificate->update([
            'gogetssl_order_id' => $goGetSSLOrder['order_id'],
            'status' => 'processing'
        ]);

        // Monitor certificate issuance
        dispatch(new MonitorCertificateIssuance($this->certificate))
            ->delay(now()->addMinutes(5));
    }

    private function generateCSR(string $domain): string
    {
        // Generate private key and CSR
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

    private function getProductId(string $certificateType): int
    {
        return match ($certificateType) {
            'DV' => 1, // GoGetSSL DV SSL
            'OV' => 2, // GoGetSSL OV SSL
            'EV' => 3, // GoGetSSL EV SSL
            default => 1
        };
    }
}
