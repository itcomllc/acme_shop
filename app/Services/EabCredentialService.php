<?php

namespace App\Services;

use App\Models\{AcmeAccount, AcmeOrder, AcmeAuthorization, AcmeChallenge};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\EabCredential;
use App\Models\Subscription;


/**
 * EAB管理サービス
 */
class EabCredentialService
{
    /**
     * サブスクリプション作成時にEAB認証情報を生成
     */
    public function createEabCredentials(Subscription $subscription): EabCredential
    {
        return EabCredential::create([
            'subscription_id' => $subscription->id,
            'mac_id' => $this->generateMacId(),
            'mac_key' => $this->generateMacKey(),
            'is_active' => true,
            'created_at' => now()
        ]);
    }

    private function generateMacId(): string
    {
        return 'eab_' . bin2hex(random_bytes(16));
    }

    private function generateMacKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
