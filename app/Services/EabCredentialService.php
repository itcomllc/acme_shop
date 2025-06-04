<?php

namespace App\Services;

use App\Models\{EabCredential, Subscription};
use Illuminate\Support\Facades\{Log, Cache};
use Illuminate\Support\Str;

/**
 * EAB Credential Service
 * ACME External Account Binding認証情報の管理
 */
class EabCredentialService
{
    /**
     * サブスクリプション用のEAB認証情報を作成
     */
    public function createEabCredentials(Subscription $subscription): EabCredential
    {
        // プランごとの最大認証情報数をチェック
        $maxCredentials = $this->getMaxCredentialsForPlan($subscription->plan_type);
        $activeCredentials = $subscription->eabCredentials()->where('is_active', true)->count();

        if ($activeCredentials >= $maxCredentials) {
            throw new \Exception("Maximum EAB credentials limit reached for plan: {$subscription->plan_type}");
        }

        // 新しいEAB認証情報を生成
        $eabCredential = EabCredential::create([
            'subscription_id' => $subscription->id,
            'mac_id' => $this->generateMacId(),
            'mac_key' => $this->generateMacKey(),
            'is_active' => true,
            'usage_count' => 0
        ]);

        // キャッシュをクリア
        $this->clearSubscriptionCache($subscription->id);

        Log::info('EAB credential created', [
            'subscription_id' => $subscription->id,
            'eab_credential_id' => $eabCredential->id,
            'mac_id' => $eabCredential->mac_id,
            'plan_type' => $subscription->plan_type
        ]);

        return $eabCredential;
    }

    /**
     * EAB認証情報を無効化
     */
    public function revokeEabCredential(EabCredential $credential): void
    {
        if (!$credential->is_active) {
            throw new \Exception('EAB credential is already revoked');
        }

        $credential->update(['is_active' => false]);

        // 関連するACMEアカウントも無効化
        $credential->acmeAccounts()->update(['status' => 'revoked']);

        // キャッシュをクリア
        $this->clearSubscriptionCache($credential->subscription_id);

        Log::info('EAB credential revoked', [
            'eab_credential_id' => $credential->id,
            'mac_id' => $credential->mac_id,
            'subscription_id' => $credential->subscription_id
        ]);
    }

    /**
     * MAC IDを生成
     */
    private function generateMacId(): string
    {
        do {
            $macId = 'eab_' . Str::random(32);
        } while (EabCredential::where('mac_id', $macId)->exists());

        return $macId;
    }

    /**
     * MAC Keyを生成
     */
    private function generateMacKey(): string
    {
        // RFC 8739準拠の強力なMAC Keyを生成（256bit）
        return base64_encode(random_bytes(32));
    }

    /**
     * プランごとの最大EAB認証情報数を取得
     */
    private function getMaxCredentialsForPlan(string $planType): int
    {
        return match ($planType) {
            'basic' => 2,
            'professional' => 5,
            'enterprise' => 20,
            default => 1
        };
    }

    /**
     * MAC IDでEAB認証情報を検索
     */
    public function findByMacId(string $macId): ?EabCredential
    {
        return EabCredential::where('mac_id', $macId)
                           ->where('is_active', true)
                           ->first();
    }

    /**
     * EAB認証情報の検証
     */
    public function validateEabCredential(string $macId, string $macKey): bool
    {
        $credential = $this->findByMacId($macId);

        if (!$credential) {
            return false;
        }

        // MAC Keyの検証
        if (!hash_equals($credential->mac_key, $macKey)) {
            return false;
        }

        // サブスクリプションの状態確認
        if (!$credential->subscription->isActive()) {
            return false;
        }

        return true;
    }

    /**
     * EAB認証情報の使用記録
     */
    public function recordUsage(EabCredential $credential): void
    {
        $credential->increment('usage_count');
        $credential->update(['last_used_at' => now()]);

        // 統計をキャッシュから削除
        $this->clearSubscriptionCache($credential->subscription_id);

        Log::debug('EAB credential usage recorded', [
            'eab_credential_id' => $credential->id,
            'mac_id' => $credential->mac_id,
            'usage_count' => $credential->usage_count + 1
        ]);
    }

    /**
     * サブスクリプションのEAB統計を取得
     */
    public function getSubscriptionStats(Subscription $subscription): array
    {
        $cacheKey = "eab_stats_subscription_{$subscription->id}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($subscription) {
            $credentials = $subscription->eabCredentials();

            return [
                'total_credentials' => $credentials->count(),
                'active_credentials' => $credentials->where('is_active', true)->count(),
                'revoked_credentials' => $credentials->where('is_active', false)->count(),
                'total_usage' => $credentials->sum('usage_count'),
                'max_credentials' => $this->getMaxCredentialsForPlan($subscription->plan_type),
                'last_used' => $credentials->whereNotNull('last_used_at')
                                        ->latest('last_used_at')
                                        ->first()?->last_used_at
            ];
        });
    }

    /**
     * 期限切れのEAB認証情報をクリーンアップ
     */
    public function cleanupExpiredCredentials(): int
    {
        // 非アクティブなサブスクリプションのEAB認証情報を無効化
        $expiredCredentials = EabCredential::whereHas('subscription', function ($query) {
            $query->whereIn('status', ['cancelled', 'suspended', 'expired']);
        })->where('is_active', true);

        $count = $expiredCredentials->count();

        if ($count > 0) {
            $expiredCredentials->update(['is_active' => false]);

            Log::info('Expired EAB credentials cleaned up', [
                'count' => $count
            ]);
        }

        return $count;
    }

    /**
     * システム全体のEAB統計を取得
     */
    public function getSystemStats(): array
    {
        $cacheKey = 'eab_system_stats';

        return Cache::remember($cacheKey, now()->addHour(), function () {
            return [
                'total_credentials' => EabCredential::count(),
                'active_credentials' => EabCredential::where('is_active', true)->count(),
                'total_usage' => EabCredential::sum('usage_count'),
                'credentials_by_plan' => EabCredential::join('subscriptions', 'eab_credentials.subscription_id', '=', 'subscriptions.id')
                                                     ->selectRaw('subscriptions.plan_type, COUNT(*) as count')
                                                     ->where('eab_credentials.is_active', true)
                                                     ->groupBy('subscriptions.plan_type')
                                                     ->pluck('count', 'plan_type')
                                                     ->toArray(),
                'recent_activity' => EabCredential::whereNotNull('last_used_at')
                                                 ->latest('last_used_at')
                                                 ->limit(10)
                                                 ->get(['mac_id', 'last_used_at', 'usage_count'])
                                                 ->toArray()
            ];
        });
    }

    /**
     * EAB認証情報の一括無効化
     */
    public function bulkRevokeCredentials(array $credentialIds, int $userId): array
    {
        $results = [];

        foreach ($credentialIds as $credentialId) {
            try {
                /** @var EabCredential */
                $credential = EabCredential::whereHas('subscription', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->findOrFail($credentialId);

                if ($credential->is_active) {
                    $this->revokeEabCredential($credential);
                    $results[] = [
                        'credential_id' => $credentialId,
                        'mac_id' => $credential->mac_id,
                        'status' => 'revoked'
                    ];
                } else {
                    $results[] = [
                        'credential_id' => $credentialId,
                        'mac_id' => $credential->mac_id,
                        'status' => 'already_revoked'
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'credential_id' => $credentialId,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * HMAC-SHA256署名の検証
     */
    public function verifyHmacSignature(string $payload, string $signature, string $macKey): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, base64_decode($macKey), true);
        $expectedSignatureBase64 = base64_encode($expectedSignature);

        return hash_equals($expectedSignatureBase64, $signature);
    }

    /**
     * JWS署名用のEAB JWTペイロードを生成
     */
    public function generateEabJwtPayload(string $macId, string $accountUrl): array
    {
        return [
            'sub' => $macId,
            'aud' => $accountUrl,
            'iat' => time(),
            'exp' => time() + 300, // 5分間有効
            'jti' => Str::uuid()->toString()
        ];
    }

    /**
     * サブスクリプションキャッシュをクリア
     */
    private function clearSubscriptionCache(int $subscriptionId): void
    {
        Cache::forget("eab_stats_subscription_{$subscriptionId}");
        Cache::forget('eab_system_stats');
    }
}