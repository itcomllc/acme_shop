<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\UserWelcomeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendUserWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public bool $createdByAdmin = false
    ) {
        // キューの設定
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // ユーザーが削除されていないことを確認
            if (!$this->user->exists) {
                Log::warning('Attempted to send welcome email to deleted user', [
                    'user_id' => $this->user->id
                ]);
                return;
            }

            // ウェルカム通知を送信
            $this->user->notify(new UserWelcomeNotification($this->createdByAdmin));

            Log::info('Welcome email sent to user', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'created_by_admin' => $this->createdByAdmin
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // ジョブを失敗させる
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendUserWelcomeEmail job failed', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}