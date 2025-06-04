<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Certificate $certificate
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $expiryDate = $this->certificate->expires_at 
            ? $this->certificate->expires_at->format('Y年m月d日') 
            : '不明';

        return (new MailMessage)
            ->subject('SSL証明書の発行完了')
            ->greeting($notifiable->name . ' 様')
            ->line('SSL証明書の発行が完了しました。')
            ->line("ドメイン: {$this->certificate->domain}")
            ->line("証明書タイプ: {$this->certificate->type}")
            ->line("有効期限: {$expiryDate}")
            ->action('証明書をダウンロード', route('ssl.certificate.download', $this->certificate))
            ->line('証明書はWebサーバーにインストールしてご利用ください。')
            ->line('設定方法についてご不明な点がございましたら、サポートまでお問い合わせください。')
            ->salutation('SSL SaaS Platform チーム');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_issued',
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'expires_at' => $this->certificate->expires_at
        ];
    }
}