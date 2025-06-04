<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\{Messages\MailMessage, Notification};
use Illuminate\Support\Facades\Log;

/**
 * System Health Issue Notification
 * システム健全性問題の通知
 */
class SystemHealthIssueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $healthSummary;

    public function __construct(array $healthSummary)
    {
        $this->healthSummary = $healthSummary;
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
        $overallScore = $this->healthSummary['overall_score'];
        $overallStatus = $this->healthSummary['overall_status'];
        $criticalIssues = $this->healthSummary['critical_issues'];
        $warnings = $this->healthSummary['warnings'];

        $urgencyLevel = $this->getUrgencyLevel($overallScore);
        
        $message = (new MailMessage)
            ->subject("🚨 SSL System Health Alert - {$overallStatus} ({$overallScore}%)")
            ->greeting("System Health Alert")
            ->line("The SSL system health monitoring has detected issues that require attention.")
            ->line("**Overall Health Score:** {$overallScore}% ({$overallStatus})")
            ->line("**Last Checked:** {$this->healthSummary['last_checked']}");

        // 重大な問題を追加
        if (!empty($criticalIssues)) {
            $message->line("## Critical Issues ({$urgencyLevel})");
            foreach ($criticalIssues as $issue) {
                $message->line("❌ {$issue}");
            }
        }

        // 警告を追加
        if (!empty($warnings)) {
            $message->line("## Warnings");
            foreach ($warnings as $warning) {
                $message->line("⚠️ {$warning}");
            }
        }

        // 詳細な結果を追加
        $message->line("## Detailed Results");
        foreach ($this->healthSummary['check_results'] as $checkType => $result) {
            $statusIcon = $this->getStatusIcon($result['status']);
            $message->line("**{$checkType}**: {$statusIcon} {$result['score']}% - {$result['message']}");
        }

        // 推奨アクション
        $message->line("## Recommended Actions");
        $recommendations = $this->getRecommendations();
        foreach ($recommendations as $recommendation) {
            $message->line("• {$recommendation}");
        }

        $message->action('View System Health Dashboard', route('admin.ssl.health.detailed'))
                ->line('Please take immediate action to resolve critical issues.')
                ->line('This is an automated message from the SSL SaaS monitoring system.');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'system_health_issue',
            'overall_score' => $this->healthSummary['overall_score'],
            'overall_status' => $this->healthSummary['overall_status'],
            'critical_issues_count' => count($this->healthSummary['critical_issues']),
            'warnings_count' => count($this->healthSummary['warnings']),
            'critical_issues' => $this->healthSummary['critical_issues'],
            'warnings' => $this->healthSummary['warnings'],
            'check_results' => $this->healthSummary['check_results'],
            'last_checked' => $this->healthSummary['last_checked'],
            'urgency_level' => $this->getUrgencyLevel($this->healthSummary['overall_score'])
        ];
    }

    /**
     * 緊急度レベルを取得
     */
    private function getUrgencyLevel(int $score): string
    {
        if ($score < 30) return 'CRITICAL';
        if ($score < 50) return 'HIGH';
        if ($score < 70) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * ステータスアイコンを取得
     */
    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '❌',
            default => '❓'
        };
    }

    /**
     * 推奨アクションを取得
     */
    private function getRecommendations(): array
    {
        $recommendations = [];
        $criticalIssues = $this->healthSummary['critical_issues'];
        $checkResults = $this->healthSummary['check_results'];

        // プロバイダー関連の問題
        if (isset($checkResults['providers']) && $checkResults['providers']['status'] === 'critical') {
            $recommendations[] = 'Check SSL provider configurations and API credentials';
            $recommendations[] = 'Verify network connectivity to provider APIs';
        }

        // 証明書関連の問題
        if (isset($checkResults['certificates']) && $checkResults['certificates']['status'] === 'critical') {
            $recommendations[] = 'Review failed certificate issuances and renewal processes';
            $recommendations[] = 'Check for expired certificates and schedule renewals';
        }

        // データベース関連の問題
        if (isset($checkResults['database']) && $checkResults['database']['status'] === 'critical') {
            $recommendations[] = 'Check database connectivity and performance';
            $recommendations[] = 'Review database logs for errors';
        }

        // キュー関連の問題
        if (isset($checkResults['queues']) && $checkResults['queues']['status'] === 'critical') {
            $recommendations[] = 'Clear failed jobs and restart queue workers';
            $recommendations[] = 'Check queue worker status and capacity';
        }

        // 汎用的な推奨事項
        if (empty($recommendations)) {
            $recommendations = [
                'Review system logs for detailed error information',
                'Check server resources (CPU, memory, disk space)',
                'Verify all services are running properly',
                'Contact system administrator if issues persist'
            ];
        }

        return $recommendations;
    }

    /**
     * 通知の重要度を決定
     */
    public function shouldSend($notifiable): bool
    {
        // 非常に低いスコアまたは重大な問題がある場合のみ送信
        return $this->healthSummary['overall_score'] < 50 || 
               !empty($this->healthSummary['critical_issues']);
    }

    /**
     * Get tags for the queued notification
     */
    public function tags(): array
    {
        return [
            'system-health',
            'admin-notification',
            'urgency:' . $this->getUrgencyLevel($this->healthSummary['overall_score'])
        ];
    }

    /**
     * Determine the time at which the notification should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(5);
    }

    /**
     * Handle a notification failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('System health notification failed', [
            'overall_score' => $this->healthSummary['overall_score'],
            'critical_issues_count' => count($this->healthSummary['critical_issues']),
            'error' => $exception->getMessage()
        ]);
    }
}