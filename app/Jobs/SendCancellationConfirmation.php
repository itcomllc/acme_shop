<?php

namespace App\Jobs;

use App\Models\{Subscription, Certificate};
use App\Notifications\{CancellationConfirmationNotification};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SendCancellationConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Subscription $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function handle(): void
    {
        $this->subscription->user->notify(new CancellationConfirmationNotification($this->subscription));
    }
}
