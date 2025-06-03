<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\{GoGetSSLService, AcmeService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use App\Services\SSLSaaSService;
use App\Models\CertificateRenewal;
use Illuminate\Support\Facades\Log;

class ProcessCertificateRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Certificate $certificate;

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(): void
    {
        // Verify subscription is still active
        if (!$this->certificate->subscription->isActive()) {
            return;
        }

        try {
            // Issue new certificate (same as initial issuance)
            $sslService = app(SSLSaaSService::class);
            $newCertificate = $sslService->issueCertificate(
                $this->certificate->subscription,
                $this->certificate->domain
            );

            // Mark renewal as completed
            CertificateRenewal::where('certificate_id', $this->certificate->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'new_certificate_id' => $newCertificate->id
                ]);

            Log::info('Certificate renewal initiated', [
                'old_certificate_id' => $this->certificate->id,
                'new_certificate_id' => $newCertificate->id,
                'domain' => $this->certificate->domain
            ]);

        } catch (\Exception $e) {
            // Mark renewal as failed
            CertificateRenewal::where('certificate_id', $this->certificate->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);

            Log::error('Certificate renewal failed', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
