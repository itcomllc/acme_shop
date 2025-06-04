<?php

namespace App\Jobs;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Cleanup Expired Certificates Job
 * 期限切れ証明書のクリーンアップ処理
 */
class CleanupExpiredCertificatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $retentionDays;
    private bool $dryRun;

    public function __construct(int $retentionDays = 90, bool $dryRun = false)
    {
        $this->retentionDays = $retentionDays;
        $this->dryRun = $dryRun;
    }

    public function handle(): void
    {
        try {
            Log::info('Starting expired certificates cleanup', [
                'retention_days' => $this->retentionDays,
                'dry_run' => $this->dryRun
            ]);

            $cutoffDate = now()->subDays($this->retentionDays);
            
            // 期限切れ証明書を取得
            $expiredCertificates = Certificate::where('expires_at', '<', $cutoffDate)
                ->whereIn('status', [
                    Certificate::STATUS_EXPIRED,
                    Certificate::STATUS_REVOKED,
                    Certificate::STATUS_REPLACED
                ])
                ->get();

            $cleanupStats = [
                'total_found' => $expiredCertificates->count(),
                'cleaned_up' => 0,
                'failed_cleanup' => 0,
                'data_archived' => 0
            ];

            foreach ($expiredCertificates as $certificate) {
                try {
                    if ($this->shouldCleanupCertificate($certificate)) {
                        if (!$this->dryRun) {
                            $this->archiveCertificateData($certificate);
                            $this->cleanupCertificateData($certificate);
                            $cleanupStats['cleaned_up']++;
                        } else {
                            Log::info('Would cleanup certificate (dry run)', [
                                'certificate_id' => $certificate->id,
                                'domain' => $certificate->domain,
                                'expired_at' => $certificate->expires_at?->toISOString()
                            ]);
                            $cleanupStats['cleaned_up']++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to cleanup certificate', [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage()
                    ]);
                    $cleanupStats['failed_cleanup']++;
                }
            }

            Log::info('Expired certificates cleanup completed', [
                'stats' => $cleanupStats,
                'dry_run' => $this->dryRun
            ]);

        } catch (\Exception $e) {
            Log::error('Expired certificates cleanup job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 証明書をクリーンアップすべきかチェック
     */
    private function shouldCleanupCertificate(Certificate $certificate): bool
    {
        // アクティブな証明書はクリーンアップしない
        if ($certificate->status === Certificate::STATUS_ISSUED) {
            return false;
        }

        // 最近交換された証明書は保持
        if ($certificate->replaced_at && $certificate->replaced_at->isAfter(now()->subDays(30))) {
            return false;
        }

        // アクティブなサブスクリプションに関連する証明書は慎重に処理
        if ($certificate->subscription && $certificate->subscription->isActive()) {
            return $certificate->expires_at && $certificate->expires_at->isBefore(now()->subDays($this->retentionDays));
        }

        return true;
    }

    /**
     * 証明書データをアーカイブ
     */
    private function archiveCertificateData(Certificate $certificate): void
    {
        // 重要なデータを監査ログ用にアーカイブ
        Log::channel('ssl')->info('Certificate data archived before cleanup', [
            'certificate_id' => $certificate->id,
            'domain' => $certificate->domain,
            'provider' => $certificate->provider,
            'status' => $certificate->status,
            'issued_at' => $certificate->issued_at?->toISOString(),
            'expires_at' => $certificate->expires_at?->toISOString(),
            'revoked_at' => $certificate->revoked_at?->toISOString(),
            'subscription_id' => $certificate->subscription_id,
            'provider_certificate_id' => $certificate->provider_certificate_id
        ]);
    }

    /**
     * 証明書データをクリーンアップ
     */
    private function cleanupCertificateData(Certificate $certificate): void
    {
        // 機密データを削除
        $certificate->update([
            'private_key' => null,
            'certificate_data' => null,
            'provider_data' => array_merge(
                $certificate->provider_data ?? [],
                ['cleaned_up_at' => now()->toISOString()]
            )
        ]);

        // 関連する検証レコードも削除
        $certificate->validationRecords()->delete();

        Log::info('Certificate data cleaned up', [
            'certificate_id' => $certificate->id,
            'domain' => $certificate->domain
        ]);
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Cleanup expired certificates job failed completely', [
            'retention_days' => $this->retentionDays,
            'dry_run' => $this->dryRun,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}