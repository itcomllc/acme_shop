<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CancellationConfirmationNotification extends Notification
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
        $planNames = [
            'basic' => 'ベーシック',
            'professional' => 'プロフェッショナル',
            'enterprise' => 'エンタープライズ'
        ];

        return (new MailMessage)
            ->subject('サブスクリプション解約完了のお知らせ - SSL SaaSプラットフォーム')
            ->greeting('サブスクリプションが解約されました')
            ->line($planNames[$this->subscription->plan_type] . 'プランのサブスクリプションが解約されました。')
            ->line('**解約処理について**')
            ->line('解約日時：' . $this->subscription->cancelled_at->format('Y年n月j日 H:i'))
            ->line('すべてのアクティブなSSL証明書が失効されました。')
            ->line('**データの取り扱い**')
            ->line('お客様のデータはコンプライアンス要件に従って適切にアーカイブされます。')
            ->line('個人情報は当社のプライバシーポリシーに従って処理されます。')
            ->line('**再開について**')
            ->line('SSL SaaSプラットフォームのご利用をお止めいただき、残念に思います。')
            ->line('今後再びご利用を希望される場合は、いつでも新規サブスクリプションをお申し込みいただけます。')
            ->action('再度ご利用を開始', url('/ssl/dashboard'))
            ->line('長らくSSL SaaSプラットフォームをご利用いただき、ありがとうございました。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}
