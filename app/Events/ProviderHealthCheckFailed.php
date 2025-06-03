<?php

namespace App\Events;

use App\Models\Certificate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Provider Health Check Failed Event
 */
class ProviderHealthCheckFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $provider,
        public string $error
    ) {}
}