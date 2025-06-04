<?php

namespace App\Jobs;

use App\Models\{Certificate, Subscription};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Log, Storage, Crypt};
use Carbon\Carbon;

/**
 * Backup Certificate Data Job
 * 証明書データのバックアップ処理
 */
class BackupCertificateDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $backupType;
    private array $options;
    private bool $includePrivateKeys;

    public function __construct(string $backupType = 'full', array $options = [], bool $includePrivateKeys = false)
    {
        $this->backupType = $backupType;
        $this->options = $options;
        $this->includePrivateKeys = $includePrivateKeys;
    }

    public function handle(): void
    {
        try {
            Log::info('Starting certificate data backup', [
                'backup_type' => $this->backupType,
                'include_private_keys' => $this->includePrivateKeys,
                'options' => $this->options
            ]);

            $backupData = $this->collectBackupData();
            $backupPath = $this->createBackup($backupData);
            $this->verifyBackup($backupPath);
            $this->cleanupOldBackups();

            Log::info('Certificate data backup completed successfully', [
                'backup_type' => $this->backupType,
                'backup_path' => $backupPath,
                'certificates_backed_up' => count($backupData['certificates'] ?? [])
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate data backup failed', [
                'backup_type' => $this->backupType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * バックアップデータを収集
     */
    private function collectBackupData(): array
    {
        $data = [
            'backup_info' => [
                'created_at' => now()->toISOString(),
                'backup_type' => $this->backupType,
                'include_private_keys' => $this->includePrivateKeys,
                'version' => '1.0'
            ],
            'certificates' => [],
            'subscriptions' => [],
            'statistics' => []
        ];

        switch ($this->backupType) {
            case 'full':
                $data = $this->collectFullBackup($data);
                break;
            case 'incremental':
                $data = $this->collectIncrementalBackup($data);
                break;
            case 'active_only':
                $data = $this->collectActiveOnlyBackup($data);
                break;
            case 'subscription':
                $data = $this->collectSubscriptionBackup($data);
                break;
            default:
                throw new \InvalidArgumentException("Unknown backup type: {$this->backupType}");
        }

        return $data;
    }

    /**
     * フルバックアップ収集
     */
    private function collectFullBackup(array $data): array
    {
        Log::info('Collecting full backup data');

        $certificates = Certificate::with(['subscription', 'validationRecords', 'renewals'])->get();
        $subscriptions = Subscription::with(['user', 'payments'])->get();

        foreach ($certificates as $certificate) {
            $data['certificates'][] = $this->prepareCertificateData($certificate);
        }

        foreach ($subscriptions as $subscription) {
            $data['subscriptions'][] = $this->prepareSubscriptionData($subscription);
        }

        $data['statistics'] = $this->collectStatistics();

        return $data;
    }

    /**
     * 増分バックアップ収集
     */
    private function collectIncrementalBackup(array $data): array
    {
        $lastBackupDate = $this->options['since'] ?? now()->subDay();
        if (is_string($lastBackupDate)) {
            $lastBackupDate = Carbon::parse($lastBackupDate);
        }

        Log::info('Collecting incremental backup data', [
            'since' => $lastBackupDate->toISOString()
        ]);

        $certificates = Certificate::with(['subscription', 'validationRecords', 'renewals'])
                                  ->where('updated_at', '>=', $lastBackupDate)
                                  ->get();

        $subscriptions = Subscription::with(['user', 'payments'])
                                   ->where('updated_at', '>=', $lastBackupDate)
                                   ->get();

        foreach ($certificates as $certificate) {
            $data['certificates'][] = $this->prepareCertificateData($certificate);
        }

        foreach ($subscriptions as $subscription) {
            $data['subscriptions'][] = $this->prepareSubscriptionData($subscription);
        }

        $data['backup_info']['incremental_since'] = $lastBackupDate->toISOString();

        return $data;
    }

    /**
     * アクティブ証明書のみバックアップ収集
     */
    private function collectActiveOnlyBackup(array $data): array
    {
        Log::info('Collecting active certificates backup data');

        $certificates = Certificate::with(['subscription', 'validationRecords'])
                                  ->where('status', Certificate::STATUS_ISSUED)
                                  ->whereNull('revoked_at')
                                  ->where('expires_at', '>', now())
                                  ->get();

        $subscriptionIds = $certificates->pluck('subscription_id')->unique();
        $subscriptions = Subscription::with(['user'])->whereIn('id', $subscriptionIds)->get();

        foreach ($certificates as $certificate) {
            $data['certificates'][] = $this->prepareCertificateData($certificate);
        }

        foreach ($subscriptions as $subscription) {
            $data['subscriptions'][] = $this->prepareSubscriptionData($subscription);
        }

        return $data;
    }

    /**
     * 特定サブスクリプションのバックアップ収集
     */
    private function collectSubscriptionBackup(array $data): array
    {
        $subscriptionId = $this->options['subscription_id'] ?? null;
        if (!$subscriptionId) {
            throw new \InvalidArgumentException('subscription_id is required for subscription backup');
        }

        Log::info('Collecting subscription backup data', [
            'subscription_id' => $subscriptionId
        ]);

        $subscription = Subscription::with(['user', 'payments', 'certificates.validationRecords'])
                                  ->findOrFail($subscriptionId);

        $data['subscriptions'][] = $this->prepareSubscriptionData($subscription);

        foreach ($subscription->certificates as $certificate) {
            $data['certificates'][] = $this->prepareCertificateData($certificate);
        }

        return $data;
    }

    /**
     * 証明書データを準備
     */
    private function prepareCertificateData(Certificate $certificate): array
    {
        $certData = [
            'id' => $certificate->id,
            'subscription_id' => $certificate->subscription_id,
            'domain' => $certificate->domain,
            'type' => $certificate->type,
            'provider' => $certificate->provider,
            'provider_certificate_id' => $certificate->provider_certificate_id,
            'status' => $certificate->status,
            'expires_at' => $certificate->expires_at?->toISOString(),
            'issued_at' => $certificate->issued_at?->toISOString(),
            'revoked_at' => $certificate->revoked_at?->toISOString(),
            'revocation_reason' => $certificate->revocation_reason,
            'created_at' => $certificate->created_at->toISOString(),
            'updated_at' => $certificate->updated_at->toISOString(),
        ];

        // 証明書データを含める
        if ($certificate->certificate_data) {
            $certData['certificate_data'] = $certificate->certificate_data;
        }

        // プロバイダーデータを含める
        if ($certificate->provider_data) {
            $certData['provider_data'] = $certificate->provider_data;
        }

        // プライベートキーを含める（オプション）
        if ($this->includePrivateKeys && $certificate->private_key) {
            try {
                // 既に暗号化されているプライベートキーを復号化してから再暗号化
                $decryptedKey = decrypt($certificate->private_key);
                $certData['private_key'] = $this->encryptForBackup($decryptedKey);
                $certData['private_key_encrypted'] = true;
            } catch (\Exception $e) {
                Log::warning('Failed to include private key in backup', [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage()
                ]);
                $certData['private_key_error'] = 'Failed to decrypt private key';
            }
        }

        // 検証レコードを含める
        if ($certificate->relationLoaded('validationRecords')) {
            $certData['validation_records'] = $certificate->validationRecords->map(function ($record) {
                return [
                    'id' => $record->id,
                    'type' => $record->type,
                    'token' => $record->token,
                    'status' => $record->status,
                    'validated_at' => $record->validated_at?->toISOString(),
                    'created_at' => $record->created_at->toISOString(),
                ];
            })->toArray();
        }

        // 更新履歴を含める
        if ($certificate->relationLoaded('renewals')) {
            $certData['renewals'] = $certificate->renewals->map(function ($renewal) {
                return [
                    'id' => $renewal->id,
                    'status' => $renewal->status,
                    'scheduled_at' => $renewal->scheduled_at?->toISOString(),
                    'completed_at' => $renewal->completed_at?->toISOString(),
                    'created_at' => $renewal->created_at->toISOString(),
                ];
            })->toArray();
        }

        return $certData;
    }

    /**
     * サブスクリプションデータを準備
     */
    private function prepareSubscriptionData(Subscription $subscription): array
    {
        $subData = [
            'id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'plan_type' => $subscription->plan_type,
            'status' => $subscription->status,
            'max_domains' => $subscription->max_domains,
            'certificate_type' => $subscription->certificate_type,
            'billing_period' => $subscription->billing_period,
            'price' => $subscription->price,
            'domains' => $subscription->domains,
            'created_at' => $subscription->created_at->toISOString(),
            'updated_at' => $subscription->updated_at->toISOString(),
        ];

        // ユーザー情報を含める
        if ($subscription->relationLoaded('user')) {
            $subData['user'] = [
                'id' => $subscription->user->id,
                'name' => $subscription->user->name,
                'email' => $subscription->user->email,
                'created_at' => $subscription->user->created_at->toISOString(),
            ];
        }

        // 支払い履歴を含める
        if ($subscription->relationLoaded('payments')) {
            $subData['payments'] = $subscription->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'created_at' => $payment->created_at->toISOString(),
                ];
            })->toArray();
        }

        return $subData;
    }

    /**
     * 統計データを収集
     */
    private function collectStatistics(): array
    {
        return [
            'total_certificates' => Certificate::count(),
            'active_certificates' => Certificate::where('status', Certificate::STATUS_ISSUED)->count(),
            'total_subscriptions' => Subscription::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'provider_distribution' => Certificate::selectRaw('provider, COUNT(*) as count')
                                                ->groupBy('provider')
                                                ->pluck('count', 'provider')
                                                ->toArray(),
            'status_distribution' => Certificate::selectRaw('status, COUNT(*) as count')
                                               ->groupBy('status')
                                               ->pluck('count', 'status')
                                               ->toArray(),
        ];
    }

    /**
     * バックアップを作成
     */
    private function createBackup(array $data): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $fileName = "ssl_backup_{$this->backupType}_{$timestamp}.json";
        $backupPath = "ssl_backups/{$fileName}";

        // JSONとして保存
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to encode backup data as JSON: ' . json_last_error_msg());
        }

        // 暗号化オプション
        if ($this->options['encrypt'] ?? false) {
            $jsonData = $this->encryptForBackup($jsonData);
            $fileName = str_replace('.json', '.encrypted', $fileName);
            $backupPath = "ssl_backups/{$fileName}";
        }

        // ファイルを保存
        Storage::disk('local')->put($backupPath, $jsonData);

        // 圧縮オプション
        if ($this->options['compress'] ?? false) {
            $compressedPath = $this->compressBackup($backupPath);
            Storage::disk('local')->delete($backupPath);
            $backupPath = $compressedPath;
        }

        Log::info('Backup file created', [
            'backup_path' => $backupPath,
            'file_size' => Storage::disk('local')->size($backupPath)
        ]);

        return $backupPath;
    }

    /**
     * バックアップファイルを圧縮
     */
    private function compressBackup(string $backupPath): string
    {
        $content = Storage::disk('local')->get($backupPath);
        $compressed = gzencode($content, 9);
        
        $compressedPath = str_replace(['.json', '.encrypted'], ['.json.gz', '.encrypted.gz'], $backupPath);
        Storage::disk('local')->put($compressedPath, $compressed);
        
        return $compressedPath;
    }

    /**
     * バックアップデータを暗号化
     */
    private function encryptForBackup(string $data): string
    {
        return base64_encode(Crypt::encrypt($data));
    }

    /**
     * バックアップファイルを検証
     */
    private function verifyBackup(string $backupPath): void
    {
        if (!Storage::disk('local')->exists($backupPath)) {
            throw new \RuntimeException("Backup file does not exist: {$backupPath}");
        }

        $fileSize = Storage::disk('local')->size($backupPath);
        if ($fileSize === 0) {
            throw new \RuntimeException("Backup file is empty: {$backupPath}");
        }

        // 圧縮されていない場合はJSONとして読み込み可能かチェック
        if (!str_contains($backupPath, '.gz') && !str_contains($backupPath, '.encrypted')) {
            $content = Storage::disk('local')->get($backupPath);
            $decoded = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Backup file contains invalid JSON: ' . json_last_error_msg());
            }

            if (!isset($decoded['backup_info']) || !isset($decoded['certificates'])) {
                throw new \RuntimeException('Backup file has invalid structure');
            }
        }

        Log::info('Backup verification completed', [
            'backup_path' => $backupPath,
            'file_size' => $fileSize
        ]);
    }

    /**
     * 古いバックアップファイルを削除
     */
    private function cleanupOldBackups(): void
    {
        $retentionDays = $this->options['retention_days'] ?? 30;
        $cutoffDate = now()->subDays($retentionDays);

        $backupFiles = Storage::disk('local')->files('ssl_backups');
        $deletedCount = 0;

        foreach ($backupFiles as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            
            if ($lastModified < $cutoffDate->timestamp) {
                Storage::disk('local')->delete($file);
                $deletedCount++;
                
                Log::info('Old backup file deleted', [
                    'file' => $file,
                    'last_modified' => date('Y-m-d H:i:s', $lastModified)
                ]);
            }
        }

        if ($deletedCount > 0) {
            Log::info('Cleanup completed', [
                'deleted_files' => $deletedCount,
                'retention_days' => $retentionDays
            ]);
        }
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Backup certificate data job failed completely', [
            'backup_type' => $this->backupType,
            'include_private_keys' => $this->includePrivateKeys,
            'options' => $this->options,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}