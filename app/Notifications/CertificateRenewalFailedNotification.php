<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateRenewalFailedNotification extends Notification
{
    use Queueable;

    private $certificate;
    private string $errorMessage;

    public function __construct($certificate, string $errorMessage)
    {
        $this->certificate = $certificate;
        $this->errorMessage = $errorMessage;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('SSL証明書の自動更新に失敗しました - ' . $this->certificate->domain)
            ->greeting('証明書更新エラーのお知らせ')
            ->line('ドメイン「' . $this->certificate->domain . '」のSSL証明書の自動更新に失敗しました。')
            ->line('**エラー詳細**')
            ->line('ドメイン：' . $this->certificate->domain)
            ->line('有効期限：' . $this->certificate->expires_at->format('Y年n月j日'))
            ->line('エラー内容：' . $this->errorMessage)
            ->line('**対応方法**')
            ->line('1. ドメインのDNS設定を確認してください')
            ->line('2. ドメインが正常にアクセス可能か確認してください')
            ->line('3. 問題が解決しない場合は、手動で証明書を再発行してください')
            ->action('ダッシュボードで対応', url('/ssl/dashboard'))
            ->line('サポートが必要な場合は、お気軽にお問い合わせください。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}
