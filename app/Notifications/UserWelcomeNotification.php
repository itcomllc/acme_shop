<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public bool $createdByAdmin = false
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        $message = (new MailMessage)
            ->subject('SSL SaaS Platformへようこそ')
            ->greeting($notifiable->name . ' 様')
            ->line('SSL SaaS Platformにご登録いただき、ありがとうございます。');

        if ($this->createdByAdmin) {
            $message->line('お客様のアカウントは管理者により作成されました。')
                   ->line('ダッシュボードにアクセスして、SSL証明書の管理を開始できます。');
        } else {
            $message->line('アカウントの作成が正常に完了しました。')
                   ->line('SSL証明書管理機能をご利用いただけます。');
        }

        $message->action('ダッシュボードへ', route('dashboard'))
               ->line('ご不明な点がございましたら、お気軽にサポートチームまでお問い合わせください。')
               ->salutation('SSL SaaS Platform チーム');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user_welcome',
            'created_by_admin' => $this->createdByAdmin,
            'user_id' => $notifiable->id,
            'sent_at' => now()
        ];
    }
}