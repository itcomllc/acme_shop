<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateExpiringNotification extends Notification
{
    use Queueable;

    private $certificate;
    private int $daysUntilExpiry;

    public function __construct($certificate, int $daysUntilExpiry)
    {
        $this->certificate = $certificate;
        $this->daysUntilExpiry = $daysUntilExpiry;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('SSL証明書の有効期限が近づいています - ' . $this->certificate->domain)
            ->greeting('SSL証明書の有効期限について')
            ->line('ドメイン「' . $this->certificate->domain . '」のSSL証明書の有効期限が近づいています。')
            ->line('**証明書情報**')
            ->line('ドメイン：' . $this->certificate->domain)
            ->line('有効期限：' . $this->certificate->expires_at->format('Y年n月j日'))
            ->line('残り日数：' . $this->daysUntilExpiry . '日')
            ->line('**自動更新について**')
            ->line('アクティブなサブスクリプションをお持ちの場合、証明書は自動的に更新されます。')
            ->line('更新処理は有効期限の30日前から開始されます。')
            ->action('ダッシュボードで確認', url('/ssl/dashboard'))
            ->line('サブスクリプションが停止されている場合は、更新されませんのでご注意ください。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}
