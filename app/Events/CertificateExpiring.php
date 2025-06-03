<?php

namespace App\Events;

use App\Models\Certificate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Certificate Expiring Event
 */
class CertificateExpiring
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Certificate $certificate,
        public int $daysUntilExpiry
    ) {}
}
