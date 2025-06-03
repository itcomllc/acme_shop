<?php

namespace App\Events;

use App\Models\Certificate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Certificate Revoked Event
 */
class CertificateRevoked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Certificate $certificate,
        public string $reason
    ) {}
}