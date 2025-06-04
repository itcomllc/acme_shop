<?php

namespace App\Jobs;

use App\Models\{Certificate, Subscription};
use App\Services\{CertificateProviderFactory, EnhancedSSLSaaSService};
use App\Events\{CertificateStatusUpdated, CertificateExpiring};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Sync Provider Certificates Job
 * プロバイダーと証明書ステータスを同期
 */
class SyncProviderCertificatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private ?string $provider;
    private ?int $subscriptionId;
    private int $batchSize;

    public function __construct(?string $provider = null, ?int $subscriptionId = null, int $batchSize = 50)
    {
        $this->provider = $provider;
        $this->subscriptionId = $subscriptionId;
        $this->batchSize = $batchSize;
    }

    public function handle(CertificateProviderFactory $providerFactory): void
    {
        try {
            Log::info('Starting provider certificate sync', [
                'provider' => $this->provider,
                'subscription_id' => $this->subscriptionId,
                'batch_size' => $this->batchSize
            ]);

            $query = Certificate::whereNotIn('status', [
                Certificate::STATUS_FAILED,
                Certificate::STATUS_REVOKED,
                Certificate::STATUS_REPLACED
            ]);

            // プロバイダーでフィルタ
            if ($this->provider) {
                $query->where('provider', $this->provider);
            }

            // サブスクリプションでフィルタ
            if ($this->subscriptionId) {
                $query->where('subscription_id', $this->subscriptionId);
            }

            $syncStats = [
                'total_processed' => 0,
                'status_updated' => 0,
                'expiry_updated' => 0,
                'sync_failed' => 0,
                'new_expiring' => 0
            ];

            $query->chunk($this->batchSize, function ($certificates) use ($providerFactory, &$syncStats) {
                foreach ($certificates as $certificate) {
                    try {
                        $result = $this->syncCertificateWithProvider($certificate, $providerFactory);
                        $syncStats['total_processed']++;
                        
                        if ($result['status_updated']) {
                            $syncStats['status_updated']++;
                        }
                        
                        if ($result['expiry_updated']) {
                            $syncStats['expiry_updated']++;
                        }
                        
                        if ($result['now_expiring']) {
                            $syncStats['new_expiring']++;
                        }

                    } catch (\Exception $e) {
                        Log::error('Failed to sync certificate with provider', [
                            'certificate_id' => $certificate->id,
                            'provider' => $certificate->provider,
                            'error' => $e->getMessage()
                        ]);
                        $syncStats['sync_failed']++;
                    }
                }
            });

            Log::info('Provider certificate sync completed', [
                'stats' => $syncStats,
                'provider' => $this->provider,
                'subscription_id' => $this->subscriptionId
            ]);

        } catch (\Exception $e) {
            Log::error('Provider certificate sync job failed', [
                'provider' => $this->provider,
                'subscription_id' => $this->subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * プロバイダーと証明書を同期
     */
    private function syncCertificateWithProvider(Certificate $certificate, CertificateProviderFactory $providerFactory): array
    {
        $result = [
            'status_updated' => false,
            'expiry_updated' => false,
            'now_expiring' => false
        ];

        try {
            $provider = $providerFactory->createProvider($certificate->provider);
            $providerStatus = $this->getProviderCertificateStatus($certificate, $provider);

            if (!$providerStatus) {
                return $result;
            }

            $oldStatus = $certificate->status;
            $oldExpiresAt = $certificate->expires_at;

            // ステータス更新
            if (isset($providerStatus['status']) && $providerStatus['status'] !== $certificate->status) {
                $certificate->update(['status' => $providerStatus['status']]);
                $result['status_updated'] = true;

                Log::info('Certificate status synced from provider', [
                    'certificate_id' => $certificate->id,
                    'old_status' => $oldStatus,
                    'new_status' => $providerStatus['status'],
                    'provider' => $certificate->provider
                ]);

                // ステータス更新イベント発火
                CertificateStatusUpdated::dispatch($certificate, $oldStatus, $providerStatus['status']);
            }

            // 有効期限更新
            if (isset($providerStatus['expires_at'])) {
                $newExpiresAt = Carbon::parse($providerStatus['expires_at']);
                
                if (!$certificate->expires_at || !$certificate->expires_at->equalTo($newExpiresAt)) {
                    $certificate->update(['expires_at' => $newExpiresAt]);
                    $result['expiry_updated'] = true;

                    Log::info('Certificate expiry date synced from provider', [
                        'certificate_id' => $certificate->id,
                        'old_expires_at' => $oldExpiresAt?->toISOString(),
                        'new_expires_at' => $newExpiresAt->toISOString(),
                        'provider' => $certificate->provider
                    ]);
                }
            }

            // 期限切れ間近チェック
            if ($certificate->isExpiringSoon() && (!$oldExpiresAt || !$oldExpiresAt->equalTo($certificate->expires_at))) {
                $result['now_expiring'] = true;
                CertificateExpiring::dispatch($certificate, $certificate->getDaysUntilExpiration());
            }

            // プロバイダーデータ更新
            if (isset($providerStatus['provider_data'])) {
                $certificate->update([
                    'provider_data' => array_merge(
                        $certificate->provider_data ?? [],
                        $providerStatus['provider_data'],
                        ['last_synced_at' => now()->toISOString()]
                    )
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Provider sync failed for certificate', [
                'certificate_id' => $certificate->id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * プロバイダーから証明書ステータスを取得
     */
    private function getProviderCertificateStatus(Certificate $certificate, $provider): ?array
    {
        switch ($certificate->provider) {
            case Certificate::PROVIDER_GOGETSSL:
                return $this->getGoGetSSLCertificateStatus($certificate, $provider);
                
            case Certificate::PROVIDER_GOOGLE_CM:
                return $this->getGoogleCMCertificateStatus($certificate, $provider);
                
            case Certificate::PROVIDER_LETS_ENCRYPT:
                return $this->getLetsEncryptCertificateStatus($certificate, $provider);
                
            default:
                Log::warning('Unknown provider for certificate sync', [
                    'certificate_id' => $certificate->id,
                    'provider' => $certificate->provider
                ]);
                return null;
        }
    }

    /**
     * GoGetSSL証明書ステータス取得
     */
    private function getGoGetSSLCertificateStatus(Certificate $certificate, $provider): ?array
    {
        if (!$certificate->provider_certificate_id && !$certificate->gogetssl_order_id) {
            return null;
        }

        $orderId = $certificate->provider_certificate_id ?? $certificate->gogetssl_order_id;
        
        try {
            $orderStatus = $provider->getOrderStatus((int) $orderId);
            
            $status = match ($orderStatus['status'] ?? 'unknown') {
                'issued' => Certificate::STATUS_ISSUED,
                'processing' => Certificate::STATUS_PROCESSING,
                'pending' => Certificate::STATUS_PENDING,
                'cancelled', 'rejected' => Certificate::STATUS_FAILED,
                'expired' => Certificate::STATUS_EXPIRED,
                default => $certificate->status
            };

            $result = ['status' => $status];

            if (isset($orderStatus['valid_till'])) {
                $result['expires_at'] = $orderStatus['valid_till'];
            }

            if (isset($orderStatus)) {
                $result['provider_data'] = ['gogetssl_order_status' => $orderStatus];
            }

            return $result;

        } catch (\Exception $e) {
            Log::warning('Failed to get GoGetSSL certificate status', [
                'certificate_id' => $certificate->id,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Google Certificate Manager証明書ステータス取得
     */
    private function getGoogleCMCertificateStatus(Certificate $certificate, $provider): ?array
    {
        if (!$certificate->provider_certificate_id) {
            return null;
        }

        try {
            $certStatus = $provider->getCertificateStatus($certificate->provider_certificate_id);
            
            $status = match ($certStatus['state'] ?? 'unknown') {
                'ACTIVE' => Certificate::STATUS_ISSUED,
                'PROVISIONING' => Certificate::STATUS_PROCESSING,
                'PENDING' => Certificate::STATUS_PENDING,
                'FAILED' => Certificate::STATUS_FAILED,
                default => $certificate->status
            };

            $result = ['status' => $status];

            if (isset($certStatus['expire_time'])) {
                $result['expires_at'] = $certStatus['expire_time'];
            }

            if (isset($certStatus)) {
                $result['provider_data'] = ['google_cm_status' => $certStatus];
            }

            return $result;

        } catch (\Exception $e) {
            Log::warning('Failed to get Google CM certificate status', [
                'certificate_id' => $certificate->id,
                'google_cert_id' => $certificate->provider_certificate_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Let's Encrypt証明書ステータス取得
     */
    private function getLetsEncryptCertificateStatus(Certificate $certificate, $provider): ?array
    {
        // Let's Encryptの場合、ACMEオーダーをチェック
        if ($certificate->acme_order_id) {
            $acmeOrder = $certificate->acmeOrder;
            
            if ($acmeOrder) {
                $status = match ($acmeOrder->status) {
                    'valid' => Certificate::STATUS_ISSUED,
                    'processing' => Certificate::STATUS_PROCESSING,
                    'pending' => Certificate::STATUS_PENDING,
                    'invalid' => Certificate::STATUS_FAILED,
                    default => $certificate->status
                };

                return ['status' => $status];
            }
        }

        return null;
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Sync provider certificates job failed completely', [
            'provider' => $this->provider,
            'subscription_id' => $this->subscriptionId,
            'batch_size' => $this->batchSize,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}