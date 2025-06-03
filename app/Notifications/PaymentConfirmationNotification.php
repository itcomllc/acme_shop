<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmationNotification extends Notification
{
    use Queueable;

    private Subscription $subscription;
    private array $invoiceData;

    public function __construct(Subscription $subscription, array $invoiceData)
    {
        $this->subscription = $subscription;
        $this->invoiceData = $invoiceData;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $amount = ($this->invoiceData['primary_recipient']['amount']['amount'] ?? 0) / 100;
        
        $planNames = [
            'basic' => 'ベーシック',
            'professional' => 'プロフェッショナル',
            'enterprise' => 'エンタープライズ'
        ];
        
        return (new MailMessage)
            ->subject('お支払い完了のお知らせ - SSL SaaSプラットフォーム')
            ->greeting('お支払いが完了しました')
            ->line('この度は、SSL SaaSプラットフォームのご利用料金をお支払いいただき、ありがとうございます。')
            ->line('**お支払い詳細**')
            ->line('お支払い金額：¥' . number_format($amount * 110, 0)) // 仮想的な円換算
            ->line('ご契約プラン：' . $planNames[$this->subscription->plan_type])
            ->line('次回請求日：' . $this->subscription->next_billing_date->format('Y年n月j日'))
            ->action('ダッシュボードを確認', url('/ssl/dashboard'))
            ->line('今後ともSSL SaaSプラットフォームをよろしくお願いいたします。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}