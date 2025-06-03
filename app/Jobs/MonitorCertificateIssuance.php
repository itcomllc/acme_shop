<?php
namespace App\Jobs;

use App\Models\Certificate;
use App\Services\GoGetSSLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Monitor certificate issuance from GoGetSSL
 */
class MonitorCertificateIssuance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Certificate $certificate;
    private int $maxRetries = 60; // Monitor for 5 hours (60 * 5 min intervals)

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(GoGetSSLService $goGetSSLService): void
    {
        if (!$this->certificate->gogetssl_order_id) {
            return;
        }

        $orderStatus = $goGetSSLService->getOrderStatus($this->certificate->gogetssl_order_id);
        
        switch ($orderStatus['status'] ?? 'unknown') {
            case 'issued':
                // Download and store certificate
                $certificateData = $goGetSSLService->downloadCertificate($this->certificate->gogetssl_order_id);
                
                $this->certificate->update([
                    'status' => 'issued',
                    'certificate_data' => $certificateData,
                    'issued_at' => now(),
                    'expires_at' => now()->addDays(config('ssl.certificate_validity_days', 90))
                ]);

                // Schedule auto-renewal
                dispatch(new ScheduleCertificateRenewal($this->certificate))
                    ->delay(now()->addDays(config('ssl.auto_renewal_days', 30)));
                break;

            case 'processing':
            case 'pending':
                // Continue monitoring if not at max retries
                if ($this->attempts() < $this->maxRetries) {
                    $this->release(300); // Check again in 5 minutes
                } else {
                    $this->certificate->update(['status' => 'failed']);
                }
                break;

            case 'cancelled':
            case 'rejected':
                $this->certificate->update(['status' => 'failed']);
                break;
        }
    }
}
