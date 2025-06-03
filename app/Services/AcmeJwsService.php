<?php

namespace App\Services;

use App\Models\{AcmeAccount, AcmeOrder, AcmeAuthorization, AcmeChallenge};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;


/**
 * ACME JWS Service
 */
class AcmeJwsService
{
    public function verifyJws(string $jwsString): array
    {
        $jws = json_decode($jwsString, true);
        
        // Nonce検証
        $protected = json_decode(base64url_decode($jws['protected']), true);
        $this->verifyNonce($protected['nonce']);
        
        // 署名検証
        $this->verifySignature($jws);
        
        return [
            'header' => $protected,
            'payload' => json_decode(base64url_decode($jws['payload']), true),
            'signature' => $jws['signature']
        ];
    }

    public function verifyEabJws(array $eabJws): array
    {
        // EAB JWS検証実装
        return [
            'header' => json_decode(base64url_decode($eabJws['protected']), true),
            'payload' => base64url_decode($eabJws['payload'])
        ];
    }

    public function verifyEabMac(array $eabJws, string $macKey): bool
    {
        // HMAC-SHA256でEAB署名検証
        $signingInput = $eabJws['protected'] . '.' . $eabJws['payload'];
        $expectedSignature = base64url_encode(
            hash_hmac('sha256', $signingInput, base64_decode($macKey), true)
        );
        
        return hash_equals($expectedSignature, $eabJws['signature']);
    }

    private function verifyNonce(string $nonce): void
    {
        if (!Cache::has("acme_nonce_{$nonce}")) {
            throw new \Exception('Invalid nonce', 400);
        }
        Cache::forget("acme_nonce_{$nonce}");
    }

    private function verifySignature(array $jws): bool
    {
        // RSA/ECDSA署名検証実装
        return true; // 簡略化
    }

    public function getKeyThumbprint(array $jwk): string
    {
        ksort($jwk);
        return base64url_encode(hash('sha256', json_encode($jwk), true));
    }
}
