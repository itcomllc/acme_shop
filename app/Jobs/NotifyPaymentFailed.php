<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Notify user of payment failure
 */
class NotifyPaymentFailed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly int $failedAttempts
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->subscription->user->notify(
                new PaymentFailedNotification($this->subscription, $this->failedAttempts)
            );

            Log::info('Payment failure notification sent', [
                'subscription_id' => $this->subscription->id,
                'user_id' => $this->subscription->user_id,
                'failed_attempts' => $this->failedAttempts
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to send payment failure notification', [
                'subscription_id' => $this->subscription->id,
                'user_id' => $this->subscription->user_id,
                'failed_attempts' => $this->failedAttempts,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the queued job.
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'notification',
            'payment-failed',
            "subscription:{$this->subscription->id}",
            "user:{$this->subscription->user_id}"
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    /**
     * Determine the number of times the job may be attempted.
     */
    public function tries(): int
    {
        return 3;
    }
}