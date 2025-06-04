<?php

namespace App\Jobs;

use App\Models\{Certificate, Subscription, User};
use App\Notifications\CertificateExpiringNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Log, Notification};
use Carbon\Carbon;

/**
 * Send Certificate Expiry Notifications Job
 * 証明書の期限切れ通知を送信
 */
class SendCertificateExpiryNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $daysBeforeExpiry;
    private bool $includeAutoRenewableCerts;

    public function __construct(int $daysBeforeExpiry = 30, bool $includeAutoRenewableCerts = false)
    {
        $this->daysBeforeExpiry = $daysBeforeExpiry;
        $this->includeAutoRenewableCerts = $includeAutoRenewableCerts;
    }

    public function handle(): void
    {
        try {
            Log::info('Starting certificate expiry notifications', [
                'days_before_expiry' => $this->daysBeforeExpiry,
                'include_auto_renewable' => $this->includeAutoRenewableCerts
            ]);

            $expiringCertificates = $this->getExpiringCertificates();
            $notificationStats = [
                'total_expiring' => $expiringCertificates->count(),
                'notifications_sent' => 0,
                'failed_notifications' => 0,
                'skipped_notifications' => 0
            ];

            // ユーザーごとにグループ化して通知を送信
            $userCertificates = $expiringCertificates->groupBy(function ($certificate) {
                return $certificate->subscription->user_id;
            });

            foreach ($userCertificates as $userId => $certificates) {
                try {
                    $user = User::find($userId);
                    if (!$user) {
                        Log::warning('User not found for certificate notifications', [
                            'user_id' => $userId,
                            'certificate_count' => $certificates->count()
                        ]);
                        $notificationStats['skipped_notifications'] += $certificates->count();
                        continue;
                    }

                    // サブスクリプションがアクティブかチェック
                    $activeSubscription = $user->activeSubscription;
                    if (!$activeSubscription) {
                        Log::info('Skipping notifications for user without active subscription', [
                            'user_id' => $userId,
                            'certificate_count' => $certificates->count()
                        ]);
                        $notificationStats['skipped_notifications'] += $certificates->count();
                        continue;
                    }

                    $this->sendUserNotification($user, $certificates);
                    $notificationStats['notifications_sent'] += $certificates->count();

                } catch (\Exception $e) {
                    Log::error('Failed to send expiry notification to user', [
                        'user_id' => $userId,
                        'certificate_count' => $certificates->count(),
                        'error' => $e->getMessage()
                    ]);
                    $notificationStats['failed_notifications'] += $certificates->count();
                }
            }

            // 管理者向けサマリー通知
            $this->sendAdminSummaryNotification($expiringCertificates, $notificationStats);

            Log::info('Certificate expiry notifications completed', [
                'stats' => $notificationStats,
                'days_before_expiry' => $this->daysBeforeExpiry
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate expiry notifications job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 期限切れ間近の証明書を取得
     */
    private function getExpiringCertificates()
    {
        $query = Certificate::with(['subscription.user'])
            ->where('status', Certificate::STATUS_ISSUED)
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now()->addDays($this->daysBeforeExpiry))
            ->where('expires_at', '>', now());

        // 自動更新可能な証明書を除外するかどうか
        if (!$this->includeAutoRenewableCerts) {
            $query->where(function ($q) {
                $q->whereNotIn('provider', [
                    Certificate::PROVIDER_GOOGLE_CM,
                    Certificate::PROVIDER_LETS_ENCRYPT
                ])->orWhereHas('subscription', function ($subQuery) {
                    $subQuery->where('auto_renewal_enabled', false);
                });
            });
        }

        return $query->get();
    }

    /**
     * ユーザーに通知を送信
     */
    private function sendUserNotification(User $user, $certificates): void
    {
        // 期限別にグループ化
        $certificatesByExpiry = $certificates->groupBy(function ($certificate) {
            $daysLeft = $certificate->getDaysUntilExpiration();
            
            if ($daysLeft <= 7) {
                return 'critical'; // 7日以内
            } elseif ($daysLeft <= 14) {
                return 'urgent'; // 14日以内
            } else {
                return 'reminder'; // 30日以内
            }
        });

        foreach ($certificatesByExpiry as $urgency => $urgencyCertificates) {
            $user->notify(new CertificateExpiringNotification(
                $urgencyCertificates->toArray(),
                $urgency,
                $this->daysBeforeExpiry
            ));

            Log::info('Certificate expiry notification sent', [
                'user_id' => $user->id,
                'urgency' => $urgency,
                'certificate_count' => $urgencyCertificates->count(),
                'domains' => $urgencyCertificates->pluck('domain')->toArray()
            ]);
        }
    }

    /**
     * 管理者向けサマリー通知を送信
     */
    private function sendAdminSummaryNotification($expiringCertificates, array $stats): void
    {
        try {
            // 管理者ユーザーを取得
            $adminUsers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['super_admin', 'admin', 'ssl_manager']);
            })->get();

            if ($adminUsers->isEmpty()) {
                Log::warning('No admin users found for expiry summary notification');
                return;
            }

            $summaryData = [
                'total_expiring' => $stats['total_expiring'],
                'notifications_sent' => $stats['notifications_sent'],
                'failed_notifications' => $stats['failed_notifications'],
                'skipped_notifications' => $stats['skipped_notifications'],
                'days_threshold' => $this->daysBeforeExpiry,
                'expiry_breakdown' => $this->getExpiryBreakdown($expiringCertificates),
                'provider_breakdown' => $this->getProviderBreakdown($expiringCertificates),
                'subscription_breakdown' => $this->getSubscriptionBreakdown($expiringCertificates)
            ];

            foreach ($adminUsers as $admin) {
                $admin->notify(new \App\Notifications\CertificateExpiryAdminSummaryNotification($summaryData));
            }

            Log::info('Admin summary notification sent for certificate expiries', [
                'admin_count' => $adminUsers->count(),
                'summary_data' => $summaryData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin expiry summary notification', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 期限別の内訳を取得
     */
    private function getExpiryBreakdown($certificates): array
    {
        return [
            'expires_in_7_days' => $certificates->filter(function ($cert) {
                return $cert->getDaysUntilExpiration() <= 7;
            })->count(),
            'expires_in_14_days' => $certificates->filter(function ($cert) {
                $days = $cert->getDaysUntilExpiration();
                return $days > 7 && $days <= 14;
            })->count(),
            'expires_in_30_days' => $certificates->filter(function ($cert) {
                $days = $cert->getDaysUntilExpiration();
                return $days > 14 && $days <= 30;
            })->count()
        ];
    }

    /**
     * プロバイダー別の内訳を取得
     */
    private function getProviderBreakdown($certificates): array
    {
        return $certificates->groupBy('provider')
                          ->map(function ($providerCerts) {
                              return [
                                  'count' => $providerCerts->count(),
                                  'auto_renewable' => $providerCerts->filter(function ($cert) {
                                      return $cert->supportsAutoRenewal();
                                  })->count()
                              ];
                          })
                          ->toArray();
    }

    /**
     * サブスクリプション別の内訳を取得
     */
    private function getSubscriptionBreakdown($certificates): array
    {
        return $certificates->groupBy('subscription.plan_type')
                          ->map->count()
                          ->toArray();
    }

    /**
     * 緊急度に基づく証明書の分類
     */
    private function classifyCertificatesByUrgency($certificates): array
    {
        $classified = [
            'critical' => [], // 7日以内
            'urgent' => [],   // 14日以内
            'reminder' => []  // 30日以内
        ];

        foreach ($certificates as $certificate) {
            $daysLeft = $certificate->getDaysUntilExpiration();
            
            if ($daysLeft <= 7) {
                $classified['critical'][] = $certificate;
            } elseif ($daysLeft <= 14) {
                $classified['urgent'][] = $certificate;
            } else {
                $classified['reminder'][] = $certificate;
            }
        }

        return $classified;
    }

    /**
     * 通知頻度制御（重複通知防止）
     */
    private function shouldSendNotification(Certificate $certificate, string $urgency): bool
    {
        // 最後の通知からの経過時間をチェック
        $lastNotification = $certificate->provider_data['last_expiry_notification'] ?? null;
        
        if (!$lastNotification) {
            return true;
        }

        $lastNotificationDate = Carbon::parse($lastNotification);
        $hoursSinceLastNotification = now()->diffInHours($lastNotificationDate);

        // 緊急度に応じて通知間隔を調整
        $requiredInterval = match ($urgency) {
            'critical' => 12, // 12時間間隔
            'urgent' => 24,   // 24時間間隔
            'reminder' => 72  // 72時間間隔
        };

        return $hoursSinceLastNotification >= $requiredInterval;
    }

    /**
     * 通知記録を更新
     */
    private function recordNotificationSent(Certificate $certificate): void
    {
        $providerData = $certificate->provider_data ?? [];
        $providerData['last_expiry_notification'] = now()->toISOString();
        $providerData['expiry_notification_count'] = ($providerData['expiry_notification_count'] ?? 0) + 1;

        $certificate->update(['provider_data' => $providerData]);
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Send certificate expiry notifications job failed completely', [
            'days_before_expiry' => $this->daysBeforeExpiry,
            'include_auto_renewable' => $this->includeAutoRenewableCerts,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}