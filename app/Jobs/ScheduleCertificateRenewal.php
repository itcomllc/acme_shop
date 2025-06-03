<?php

namespace App\Jobs;

use App\Models\{ Certificate, CertificateRenewal};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class ScheduleCertificateRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Certificate $certificate;

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(): void
    {
        // Check if certificate is still valid and subscription is active
        if (!$this->certificate->subscription->isActive()) {
            return;
        }

        // Create renewal record
        CertificateRenewal::create([
            'certificate_id' => $this->certificate->id,
            'status' => 'pending',
            'scheduled_at' => $this->certificate->expires_at->subDays(30) // 30 days before expiry
        ]);

        // Schedule the actual renewal job
        dispatch(new ProcessCertificateRenewal($this->certificate))
            ->delay($this->certificate->expires_at->subDays(30));
    }
}
