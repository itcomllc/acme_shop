<?php

namespace App\Notifications;

use App\Models\{Payment, Subscription};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $payment,
        public Subscription $subscription,
        public string $reason = ''
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
        $amount = number_format($this->payment->amount / 100, 0);
        $planName = ucfirst($this->subscription->plan_type) . 'プラン';

        $message = (new MailMessage)
            ->subject('【重要】お支払いに失敗しました')
            ->greeting($notifiable->name . ' 様')
            ->line('SSL SaaS Platformでのお支払い処理に失敗しました。')
            ->line("プラン: {$planName}")
            ->line("請求金額: ¥{$amount}");

        if ($this->reason) {
            $message->line("失敗理由: {$this->reason}");
        }

        $message->line('サービスの継続利用のため、お支払い方法を確認し、再度お支払いを行ってください。')
               ->action('お支払い方法を確認', route('ssl.billing.index'))
               ->line('お支払いの問題が解決されない場合は、サポートまでお問い合わせください。')
               ->line('ご不便をおかけして申し訳ございません。')
               ->salutation('SSL SaaS Platform チーム');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'payment_id' => $this->payment->id,
            'subscription_id' => $this->subscription->id,
            'amount' => $this->payment->amount,
            'reason' => $this->reason,
            'failed_at' => now()
        ];
    }
}