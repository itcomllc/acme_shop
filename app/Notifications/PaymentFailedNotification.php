<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly int $failedAttempts
    ) {}

    /**
     * Get the notification's delivery channels.
     * @param mixed $notifiable
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $planNames = [
            'basic' => 'ベーシック',
            'professional' => 'プロフェッショナル',
            'enterprise' => 'エンタープライズ'
        ];

        $planName = $planNames[$this->subscription->plan_type] ?? ucfirst($this->subscription->plan_type);
        $message = (new MailMessage)
            ->subject('お支払いエラーのお知らせ - SSL SaaSプラットフォーム')
            ->greeting('お支払いの処理でエラーが発生しました')
            ->line("{$planName}プランのお支払い処理でエラーが発生いたしました。")
            ->line("失敗回数：{$this->failedAttempts}/3回");

        if ($this->failedAttempts >= 3) {
            $message
                ->line('**重要：サービス停止のお知らせ**')
                ->line('お支払いエラーが3回連続で発生したため、一時的にサービスを停止させていただきました。')
                ->line('サービスを再開するには、お支払い方法を更新してください。')
                ->action('お支払い方法を更新', route('billing.index'))
                ->line('**影響について**')
                ->line('・新しい証明書の発行が停止されます')
                ->line('・既存の証明書は有効期限まで継続します')
                ->line('・自動更新が停止されます');
        } else {
            $message
                ->line('24時間後に自動的に再度お支払い処理を行います。')
                ->line('お急ぎの場合は、お支払い方法を手動で更新していただけます。')
                ->action('お支払い方法を更新', route('billing.index'));
        }

        return $message
            ->line('ご不便をおかけして申し訳ございません。ご不明な点がございましたら、サポートチームまでお問い合わせください。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }

    /**
     * Get the array representation of the notification.
     * @param mixed $notifiable
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_type' => $this->subscription->plan_type,
            'failed_attempts' => $this->failedAttempts,
            'is_suspended' => $this->failedAttempts >= 3,
            'message' => "お支払いエラーが{$this->failedAttempts}回発生しました",
        ];
    }

    /**
     * Determine if the notification should be queued.
     */
    public function shouldQueue(): bool
    {
        return true;
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
}