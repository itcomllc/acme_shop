<?php

namespace App\Jobs;

use App\Models\{ Certificate};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use App\Services\GoGetSSLService;
use Illuminate\Support\Facades\Log;

class RevokeCertificate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Certificate $certificate;

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(): void
    {
        try {
            // Revoke certificate via GoGetSSL if it has an order ID
            if ($this->certificate->gogetssl_order_id) {
                $goGetSSLService = app(GoGetSSLService::class);
                $goGetSSLService->revokeCertificate($this->certificate->gogetssl_order_id, 'subscription_cancelled');
            }

            // Update certificate status
            $this->certificate->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revocation_reason' => 'subscription_cancelled'
            ]);

            Log::info('Certificate revoked successfully', [
                'certificate_id' => $this->certificate->id,
                'domain' => $this->certificate->domain
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revoke certificate', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
