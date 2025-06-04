<?php

namespace App\Jobs;

use App\Models\{AcmeChallenge, AcmeAuthorization, AcmeOrder};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Http, Log};

/**
 * ACME Challenge Validation Job
 */
class ValidateAcmeChallenge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private AcmeChallenge $challenge;

    public function __construct(AcmeChallenge $challenge)
    {
        $this->challenge = $challenge;
    }

    public function handle(): void
    {
        try {
            Log::info('Starting ACME challenge validation', [
                'challenge_id' => $this->challenge->id,
                'type' => $this->challenge->type,
                'domain' => $this->getDomain()
            ]);

            $isValid = match ($this->challenge->type) {
                'http-01' => $this->validateHttpChallenge(),
                'dns-01' => $this->validateDnsChallenge(),
                default => false
            };

            if ($isValid) {
                $this->markChallengeValid();
                $this->checkAuthorizationCompletion();
            } else {
                $this->markChallengeInvalid('Validation failed');
            }

        } catch (\Exception $e) {
            Log::error('ACME challenge validation failed', [
                'challenge_id' => $this->challenge->id,
                'error' => $e->getMessage()
            ]);

            $this->markChallengeInvalid($e->getMessage());
        }
    }

    /**
     * HTTP-01チャレンジ検証
     */
    private function validateHttpChallenge(): bool
    {
        $domain = $this->getDomain();
        $token = $this->challenge->token;
        $expectedKeyAuth = $this->challenge->key_authorization;

        $url = "http://{$domain}/.well-known/acme-challenge/{$token}";

        try {
            Log::info('Validating HTTP-01 challenge', [
                'url' => $url,
                'expected_key_auth' => $expectedKeyAuth
            ]);

            $response = Http::timeout(10)
                          ->retry(3, 2000)
                          ->get($url);

            if (!$response->successful()) {
                Log::warning('HTTP-01 challenge HTTP error', [
                    'url' => $url,
                    'status' => $response->status()
                ]);
                return false;
            }

            $actualKeyAuth = trim($response->body());

            $isValid = hash_equals($expectedKeyAuth, $actualKeyAuth);

            Log::info('HTTP-01 challenge validation result', [
                'url' => $url,
                'expected' => $expectedKeyAuth,
                'actual' => $actualKeyAuth,
                'valid' => $isValid
            ]);

            return $isValid;

        } catch (\Exception $e) {
            Log::error('HTTP-01 challenge validation error', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * DNS-01チャレンジ検証
     */
    private function validateDnsChallenge(): bool
    {
        $domain = $this->getDomain();
        $keyAuth = $this->challenge->key_authorization;
        $expectedValue = base64url_encode(hash('sha256', $keyAuth, true));
        
        $recordName = "_acme-challenge.{$domain}";

        try {
            Log::info('Validating DNS-01 challenge', [
                'record_name' => $recordName,
                'expected_value' => $expectedValue
            ]);

            // DNS TXTレコードを取得
            $txtRecords = dns_get_record($recordName, DNS_TXT);

            if (!$txtRecords) {
                Log::warning('DNS-01 challenge: No TXT records found', [
                    'record_name' => $recordName
                ]);
                return false;
            }

            foreach ($txtRecords as $record) {
                $actualValue = $record['txt'] ?? '';
                
                if (hash_equals($expectedValue, $actualValue)) {
                    Log::info('DNS-01 challenge validation successful', [
                        'record_name' => $recordName,
                        'expected' => $expectedValue,
                        'actual' => $actualValue
                    ]);
                    return true;
                }
            }

            Log::warning('DNS-01 challenge: No matching TXT record found', [
                'record_name' => $recordName,
                'expected' => $expectedValue,
                'found_records' => array_column($txtRecords, 'txt')
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('DNS-01 challenge validation error', [
                'record_name' => $recordName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * チャレンジを有効としてマーク
     */
    private function markChallengeValid(): void
    {
        $this->challenge->update([
            'status' => 'valid',
            'validated' => now()
        ]);

        Log::info('ACME challenge marked as valid', [
            'challenge_id' => $this->challenge->id,
            'type' => $this->challenge->type,
            'domain' => $this->getDomain()
        ]);
    }

    /**
     * チャレンジを無効としてマーク
     */
    private function markChallengeInvalid(string $error): void
    {
        $this->challenge->update([
            'status' => 'invalid'
        ]);

        Log::warning('ACME challenge marked as invalid', [
            'challenge_id' => $this->challenge->id,
            'type' => $this->challenge->type,
            'domain' => $this->getDomain(),
            'error' => $error
        ]);
    }

    /**
     * Authorization完了チェック
     */
    private function checkAuthorizationCompletion(): void
    {
        /** @var AcmeAuthorization */
        $authorization = $this->challenge->authorization;
        
        // 全てのチャレンジをチェック
        $challenges = $authorization->challenges;
        $validChallenges = $challenges->where('status', 'valid');

        // 少なくとも1つのチャレンジが有効であればauthorizationも有効
        if ($validChallenges->isNotEmpty()) {
            $authorization->update(['status' => 'valid']);

            Log::info('ACME authorization marked as valid', [
                'authorization_id' => $authorization->id,
                'domain' => $this->getDomain()
            ]);

            // オーダーの状態をチェック
            $this->checkOrderCompletion($authorization->order);
        }
    }

    /**
     * オーダー完了チェック
     */
    private function checkOrderCompletion(AcmeOrder $order): void
    {
        $authorizations = $order->authorizations;
        $validAuthorizations = $authorizations->where('status', 'valid');

        // 全てのauthorizationが有効であればオーダーも準備完了
        if ($validAuthorizations->count() === $authorizations->count()) {
            $order->update(['status' => 'ready']);

            Log::info('ACME order marked as ready', [
                'order_id' => $order->id,
                'identifiers' => $order->identifiers
            ]);
        }
    }

    /**
     * ドメイン名を取得
     */
    private function getDomain(): string
    {
        $identifier = $this->challenge->authorization->identifier;
        return $identifier['value'] ?? 'unknown';
    }
}
