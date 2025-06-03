<?php

namespace App\Events;

use App\Models\Certificate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Subscription Provider Changed Event
 */
class SubscriptionProviderChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $subscriptionId,
        public string $oldProvider,
        public string $newProvider,
        public string $reason
    ) {}
}
