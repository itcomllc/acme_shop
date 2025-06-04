<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\{Messages\MailMessage, Notification};
use Illuminate\Support\Facades\Log;

/**
 * Certificate Expiry Admin Summary Notification
 * 証明書期限切れの管理者向けサマリー通知
 */
class CertificateExpiryAdminSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $summaryData;

    public function __construct(array $summaryData)
    {
        $this->summaryData = $summaryData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $totalExpiring = $this->summaryData['total_expiring'];
        $daysThreshold = $this->summaryData['days_threshold'];
        $expiryBreakdown = $this->summaryData['expiry_breakdown'];
        
        $urgencyLevel = $this->getUrgencyLevel();
        $subject = "📋 Certificate Expiry Summary - {$totalExpiring} certificates expiring within {$daysThreshold} days";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Certificate Expiry Summary Report")
            ->line("This is your regular summary of certificates approaching expiration.");

        // 概要統計
        $message->line("## Summary Statistics")
                ->line("**Total Expiring:** {$totalExpiring} certificates")
                ->line("**Threshold:** {$daysThreshold} days")
                ->line("**Notifications Sent:** {$this->summaryData['notifications_sent']}")
                ->line("**Failed Notifications:** {$this->summaryData['failed_notifications']}")
                ->line("**Skipped Notifications:** {$this->summaryData['skipped_notifications']}");

        // 期限別内訳
        if (!empty($expiryBreakdown)) {
            $message->line("## Expiry Breakdown");
            if ($expiryBreakdown['expires_in_7_days'] > 0) {
                $message->line("🔴 **Critical (7 days or less):** {$expiryBreakdown['expires_in_7_days']} certificates");
            }
            if ($expiryBreakdown['expires_in_14_days'] > 0) {
                $message->line("🟡 **Urgent (8-14 days):** {$expiryBreakdown['expires_in_14_days']} certificates");
            }
            if ($expiryBreakdown['expires_in_30_days'] > 0) {
                $message->line("🟢 **Reminder (15-30 days):** {$expiryBreakdown['expires_in_30_days']} certificates");
            }
        }

        // プロバイダー別内訳
        if (!empty($this->summaryData['provider_breakdown'])) {
            $message->line("## Provider Breakdown");
            foreach ($this->summaryData['provider_breakdown'] as $provider => $data) {
                $autoRenewableCount = $data['auto_renewable'] ?? 0;
                $manualCount = $data['count'] - $autoRenewableCount;
                $icon = $this->getProviderIcon($provider);
                
                $message->line("{$icon} **{$this->getProviderDisplayName($provider)}:** {$data['count']} total ({$autoRenewableCount} auto-renewable, {$manualCount} manual)");
            }
        }

        // サブスクリプションプラン別内訳
        if (!empty($this->summaryData['subscription_breakdown'])) {
            $message->line("## Subscription Plan Breakdown");
            foreach ($this->summaryData['subscription_breakdown'] as $plan => $count) {
                $message->line("• **{$this->getPlanDisplayName($plan)}:** {$count} certificates");
            }
        }

        // 推奨アクション
        $message->line("## Recommended Actions");
        $recommendations = $this->getRecommendations();
        foreach ($recommendations as $recommendation) {
            $message->line("• {$recommendation}");
        }

        // 通知統計に基づく追加情報
        if ($this->summaryData['failed_notifications'] > 0) {
            $message->line("⚠️ **Note:** {$this->summaryData['failed_notifications']} notifications failed to send. Please check the notification system.");
        }

        $message->action('View Certificate Dashboard', route('ssl.dashboard'))
                ->line('Monitor certificate status and take necessary renewal actions.')
                ->line('This is an automated summary from the SSL certificate monitoring system.');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'certificate_expiry_admin_summary',
            'total_expiring' => $this->summaryData['total_expiring'],
            'days_threshold' => $this->summaryData['days_threshold'],
            'notifications_sent' => $this->summaryData['notifications_sent'],
            'failed_notifications' => $this->summaryData['failed_notifications'],
            'skipped_notifications' => $this->summaryData['skipped_notifications'],
            'expiry_breakdown' => $this->summaryData['expiry_breakdown'],
            'provider_breakdown' => $this->summaryData['provider_breakdown'],
            'subscription_breakdown' => $this->summaryData['subscription_breakdown'],
            'urgency_level' => $this->getUrgencyLevel(),
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * 緊急度レベルを取得
     */
    private function getUrgencyLevel(): string
    {
        $criticalCount = $this->summaryData['expiry_breakdown']['expires_in_7_days'] ?? 0;
        $urgentCount = $this->summaryData['expiry_breakdown']['expires_in_14_days'] ?? 0;
        
        if ($criticalCount > 10) return 'CRITICAL';
        if ($criticalCount > 0 || $urgentCount > 20) return 'HIGH';
        if ($urgentCount > 0) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * プロバイダーアイコンを取得
     */
    private function getProviderIcon(string $provider): string
    {
        return match ($provider) {
            'gogetssl' => '🏢',
            'google_certificate_manager' => '🌐',
            'lets_encrypt' => '🔒',
            default => '📄'
        };
    }

    /**
     * プロバイダー表示名を取得
     */
    private function getProviderDisplayName(string $provider): string
    {
        return match ($provider) {
            'gogetssl' => 'GoGetSSL',
            'google_certificate_manager' => 'Google Certificate Manager',
            'lets_encrypt' => 'Let\'s Encrypt',
            default => ucfirst($provider)
        };
    }

    /**
     * プラン表示名を取得
     */
    private function getPlanDisplayName(string $plan): string
    {
        return match ($plan) {
            'basic' => 'Basic SSL',
            'professional' => 'Professional SSL',
            'enterprise' => 'Enterprise SSL',
            default => ucfirst($plan)
        };
    }

    /**
     * 推奨アクションを取得
     */
    private function getRecommendations(): array
    {
        $recommendations = [];
        $criticalCount = $this->summaryData['expiry_breakdown']['expires_in_7_days'] ?? 0;
        $urgentCount = $this->summaryData['expiry_breakdown']['expires_in_14_days'] ?? 0;
        $providerBreakdown = $this->summaryData['provider_breakdown'] ?? [];

        // 緊急対応が必要な場合
        if ($criticalCount > 0) {
            $recommendations[] = "**Immediate action required:** {$criticalCount} certificates expire within 7 days";
            $recommendations[] = "Review and manually renew critical certificates";
        }

        if ($urgentCount > 0) {
            $recommendations[] = "Schedule renewal for {$urgentCount} certificates expiring within 14 days";
        }

        // プロバイダー固有の推奨事項
        foreach ($providerBreakdown as $provider => $data) {
            $manualCount = $data['count'] - ($data['auto_renewable'] ?? 0);
            
            if ($manualCount > 0) {
                $providerName = $this->getProviderDisplayName($provider);
                $recommendations[] = "Manual renewal required for {$manualCount} {$providerName} certificates";
            }
        }

        // 自動更新の設定推奨
        $totalAutoRenewable = collect($providerBreakdown)->sum('auto_renewable');
        $totalCertificates = $this->summaryData['total_expiring'];
        $manualPercentage = $totalCertificates > 0 ? (($totalCertificates - $totalAutoRenewable) / $totalCertificates) * 100 : 0;

        if ($manualPercentage > 50) {
            $recommendations[] = "Consider enabling auto-renewal for more certificates to reduce manual overhead";
        }

        // 通知システムの問題
        if ($this->summaryData['failed_notifications'] > 5) {
            $recommendations[] = "Check notification system - multiple delivery failures detected";
        }

        // デフォルト推奨事項
        if (empty($recommendations)) {
            $recommendations = [
                "Review certificate renewal schedules",
                "Verify auto-renewal configurations",
                "Monitor certificate health regularly"
            ];
        }

        return $recommendations;
    }

    /**
     * 通知の送信条件
     */
    public function shouldSend($notifiable): bool
    {
        // 期限切れ証明書がある場合のみ送信
        return $this->summaryData['total_expiring'] > 0;
    }

    /**
     * Get tags for the queued notification
     */
    public function tags(): array
    {
        return [
            'certificate-expiry',
            'admin-summary',
            'urgency:' . $this->getUrgencyLevel(),
            'threshold:' . $this->summaryData['days_threshold']
        ];
    }

    /**
     * Determine the time at which the notification should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Handle a notification failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Certificate expiry admin summary notification failed', [
            'total_expiring' => $this->summaryData['total_expiring'],
            'days_threshold' => $this->summaryData['days_threshold'],
            'error' => $exception->getMessage()
        ]);
    }
}