<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class CertificateIssuedNotification extends Notification
{
    use Queueable;

    private $certificate;

    public function __construct($certificate)
    {
        $this->certificate = $certificate;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('SSL証明書発行完了のお知らせ - ' . $this->certificate->domain)
            ->greeting('SSL証明書が発行されました')
            ->line('ドメイン「' . $this->certificate->domain . '」のSSL証明書が正常に発行されました。')
            ->line('**証明書詳細**')
            ->line('ドメイン：' . $this->certificate->domain)
            ->line('証明書タイプ：' . $this->certificate->type)
            ->line('発行日：' . $this->certificate->issued_at->format('Y年n月j日'))
            ->line('有効期限：' . $this->certificate->expires_at->format('Y年n月j日'))
            ->action('証明書をダウンロード', url('/ssl/certificate/' . $this->certificate->id . '/download'))
            ->line('証明書は自動的に更新されますので、特別な操作は必要ありません。')
            ->salutation('SSL SaaSプラットフォーム運営チーム');
    }
}
