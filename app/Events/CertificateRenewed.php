<?php

namespace App\Events;

use App\Models\Certificate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Certificate Renewed Event
 */
class CertificateRenewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Certificate $oldCertificate,
        public Certificate $newCertificate
    ) {}
}
