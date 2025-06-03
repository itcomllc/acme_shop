<?php

namespace App\Jobs;

use App\Models\{Subscription, Certificate};
use App\Notifications\{PaymentConfirmationNotification};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SendPaymentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Subscription $subscription;
    private array $invoiceData;

    public function __construct(Subscription $subscription, array $invoiceData)
    {
        $this->subscription = $subscription;
        $this->invoiceData = $invoiceData;
    }

    public function handle(): void
    {
        $this->subscription->user->notify(new PaymentConfirmationNotification($this->subscription, $this->invoiceData));
    }
}
