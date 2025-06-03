<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
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

        $certTypes = [
            'DV' => 'ドメイン認証',
            'OV' => '組織認証',
            'EV' => '拡張認証'
        ];

        return (new MailMessage)
            ->subject('SSL SaaSプラットフォームへようこそ！')
            ->greeting('SSL SaaSプラットフォームへようこそ！')
            ->line($planNames[$this->subscription->plan_type] . 'プランにご加入いただき、ありがとうございます。')
            ->line('ご契約内容：')
            ->line('・SSL証明書：最大' . $this->subscription->max_domains . '枚')
            ->line('・認証レベル：' . $certTypes[$this->subscription->certificate_type])
            ->line('・ACME自動証明書管理')
            ->line('・99.9%稼働保証SLA')
            ->action('ダッシュボードにアクセス', url('/ssl/dashboard'))
            ->line('ご不明な点がございましたら、サポートチームまでお気軽にお問い合わせください。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}
