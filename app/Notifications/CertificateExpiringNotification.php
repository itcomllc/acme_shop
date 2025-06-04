<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Certificate $certificate,
        public int $daysUntilExpiry
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
        $expiryDate = $this->certificate->expires_at->format('Y年m月d日');
        $urgencyClass = $this->daysUntilExpiry <= 7 ? 'urgent' : 'warning';
        
        $subject = $this->daysUntilExpiry <= 7 
            ? '【緊急】SSL証明書の有効期限が迫っています' 
            : 'SSL証明書の有効期限のお知らせ';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($notifiable->name . ' 様')
            ->line("SSL証明書の有効期限が近づいています。");

        if ($this->daysUntilExpiry <= 7) {
            $message->line("⚠️ 緊急: あと{$this->daysUntilExpiry}日で期限切れとなります。");
        } else {
            $message->line("あと{$this->daysUntilExpiry}日で有効期限が切れます。");
        }

        $message->line("ドメイン: {$this->certificate->domain}")
               ->line("有効期限: {$expiryDate}")
               ->action('証明書を更新', route('ssl.certificates.show', $this->certificate))
               ->line('証明書の期限が切れる前に、必ず更新手続きを行ってください。')
               ->line('自動更新が設定されている場合は、この通知を無視していただいて構いません。')
               ->salutation('SSL SaaS Platform チーム');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_expiring',
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'days_until_expiry' => $this->daysUntilExpiry,
            'expires_at' => $this->certificate->expires_at,
            'urgency' => $this->daysUntilExpiry <= 7 ? 'urgent' : 'warning'
        ];
    }
}