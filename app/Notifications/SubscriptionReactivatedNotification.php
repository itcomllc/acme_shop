<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionReactivatedNotification extends Notification
{
    use Queueable;

    private Subscription $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('サービス再開のお知らせ - SSL SaaSプラットフォーム')
            ->greeting('サービスが再開されました')
            ->line('お客様のSSL SaaSプラットフォームサービスが正常に再開されました。')
            ->line('**再開内容**')
            ->line('・新しい証明書の発行が可能になりました')
            ->line('・証明書の自動更新が再開されました')
            ->line('・すべての機能をご利用いただけます')
            ->line('**自動更新の再開**')
            ->line('一時停止中に期限切れとなった証明書については、自動的に更新処理を開始いたします。')
            ->action('ダッシュボードで確認', url('/ssl/dashboard'))
            ->line('ご不便をおかけして申し訳ございませんでした。引き続きSSL SaaSプラットフォームをご利用ください。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}
