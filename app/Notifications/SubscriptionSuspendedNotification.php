<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspendedNotification extends Notification
{
    use Queueable;

    private Subscription $subscription;
    private string $reason;

    public function __construct(Subscription $subscription, string $reason)
    {
        $this->subscription = $subscription;
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $reasons = [
            'payment_failed' => 'お支払いエラーの連続発生',
            'fraud_detection' => '不正利用の疑い',
            'terms_violation' => '利用規約違反',
            'manual_suspension' => '管理者による手動停止'
        ];

        return (new MailMessage)
            ->subject('サービス一時停止のお知らせ - SSL SaaSプラットフォーム')
            ->greeting('サービスが一時停止されました')
            ->line('お客様のSSL SaaSプラットフォームサービスが一時停止されました。')
            ->line('**停止理由**')
            ->line($reasons[$this->reason] ?? $this->reason)
            ->line('**影響について**')
            ->line('・新しい証明書の発行が停止されます')
            ->line('・既存の証明書は有効期限まで継続します')
            ->line('・証明書の自動更新が停止されます')
            ->line('・ダッシュボードへのアクセスは可能です')
            ->action('お支払い方法を更新', url('/ssl/billing'))
            ->line('サービスの再開については、サポートチームまでお問い合わせください。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}
