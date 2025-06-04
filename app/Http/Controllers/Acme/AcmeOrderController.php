<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeAccount, AcmeOrder, AcmeAuthorization, AcmeChallenge, Subscription, EabCredential};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log};
use App\Services\{AcmeJwsService, GoGetSSLService, GoogleCertificateManagerService};

/**
 * ACME Order Controller
 */
class AcmeOrderController extends Controller
{
    public function __construct(
        private readonly AcmeJwsService $jwsService,
        private readonly GoGetSSLService $goGetSSL,
        private readonly GoogleCertificateManagerService $googleCM
    ) {}

    public function newOrder(Request $request): JsonResponse
    {
        try {
            // JWS検証・アカウント認証
            $jws = $this->jwsService->verifyJws($request->getContent());
            $account = $this->getAccountFromJws($jws);
            
            $payload = $jws['payload'];
            $identifiers = $payload['identifiers'];
            
            // ドメイン制限確認
            $this->validateDomainLimits($account->subscription, $identifiers);
            
            // ACME Order作成
            $order = AcmeOrder::create([
                'account_id' => $account->id,
                'subscription_id' => $account->subscription_id,
                'identifiers' => $identifiers,
                'status' => 'pending',
                'expires' => now()->addDays(7)
            ]);

            // Authorization & Challenge作成
            $authorizations = [];
            foreach ($identifiers as $identifier) {
                $authz = $this->createAuthorization($order, $identifier);
                $authorizations[] = route('acme.authorization', $authz->id);
            }

            // バックエンドCA選択
            $provider = $this->selectProvider($account->subscription, $identifiers);
            $order->update(['selected_provider' => $provider]);

            $orderUrl = route('acme.order', $order->id);

            Log::info('ACME order created', [
                'order_id' => $order->id,
                'account_id' => $account->id,
                'identifiers' => $identifiers,
                'provider' => $provider
            ]);

            return response()->json([
                'status' => $order->status,
                'expires' => $order->expires->toISOString(),
                'identifiers' => $order->identifiers,
                'authorizations' => $authorizations,
                'finalize' => route('acme.order.finalize', $order->id)
            ], 201)->header('Location', $orderUrl);

        } catch (\Exception $e) {
            Log::error('ACME order creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return response()->json([
                'type' => 'urn:ietf:params:acme:error:malformed',
                'detail' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function getOrder(Request $request, AcmeOrder $order): JsonResponse
    {
        try {
            // JWS検証・アカウント認証
            $jws = $this->jwsService->verifyJws($request->getContent());
            $account = $this->getAccountFromJws($jws);
            
            // オーダー所有権確認
            if ($order->account_id !== $account->id) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:unauthorized',
                    'detail' => 'Order does not belong to this account'
                ], 403);
            }

            $authorizations = $order->authorizations->map(function ($authz) {
                return route('acme.authorization', $authz->id);
            });

            $response = [
                'status' => $order->status,
                'expires' => $order->expires->toISOString(),
                'identifiers' => $order->identifiers,
                'authorizations' => $authorizations->toArray(),
                'finalize' => route('acme.order.finalize', $order->id)
            ];

            // 証明書が発行済みの場合はURLを追加
            if ($order->status === 'valid' && $order->certificate_url) {
                $response['certificate'] = $order->certificate_url;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('ACME order retrieval failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => 'Failed to retrieve order'
            ], 500);
        }
    }

    public function finalizeOrder(Request $request, AcmeOrder $order): JsonResponse
    {
        try {
            // JWS検証・アカウント認証
            $jws = $this->jwsService->verifyJws($request->getContent());
            $account = $this->getAccountFromJws($jws);
            
            // オーダー所有権確認
            if ($order->account_id !== $account->id) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:unauthorized',
                    'detail' => 'Order does not belong to this account'
                ], 403);
            }

            // オーダー状態確認
            if ($order->status !== 'ready') {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:orderNotReady',
                    'detail' => 'Order is not ready for finalization'
                ], 400);
            }

            $payload = $jws['payload'];
            $csr = $payload['csr'] ?? null;

            if (!$csr) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:malformed',
                    'detail' => 'CSR is required'
                ], 400);
            }

            // CSR検証
            $this->validateCsr($csr, $order->identifiers);

            // オーダー状態を processing に更新
            $order->update(['status' => 'processing']);

            // バックエンドプロバイダーで証明書発行
            $this->issueCertificateWithProvider($order, $csr);

            return response()->json([
                'status' => $order->fresh()->status,
                'expires' => $order->expires->toISOString(),
                'identifiers' => $order->identifiers,
                'authorizations' => $order->authorizations->map(fn($authz) => route('acme.authorization', $authz->id)),
                'finalize' => route('acme.order.finalize', $order->id)
            ]);

        } catch (\Exception $e) {
            Log::error('ACME order finalization failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => 'Failed to finalize order'
            ], 500);
        }
    }

    /**
     * JWSからアカウントを取得
     */
    private function getAccountFromJws(array $jws): AcmeAccount
    {
        $protected = $jws['header'];
        
        if (isset($protected['kid'])) {
            // アカウントURL経由
            $accountId = basename($protected['kid']);
            $account = AcmeAccount::findOrFail($accountId);
        } elseif (isset($protected['jwk'])) {
            // 公開鍵経由
            $thumbprint = $this->jwsService->getKeyThumbprint($protected['jwk']);
            $account = AcmeAccount::where('public_key_thumbprint', $thumbprint)->firstOrFail();
        } else {
            throw new \Exception('Account identification required', 400);
        }

        if ($account->status !== 'valid') {
            throw new \Exception('Account is not valid', 403);
        }

        return $account;
    }

    /**
     * オーダーのAuthorization作成
     */
    private function createAuthorization(AcmeOrder $order, array $identifier): AcmeAuthorization
    {
        $authorization = AcmeAuthorization::create([
            'order_id' => $order->id,
            'identifier' => $identifier,
            'status' => 'pending',
            'expires' => now()->addDays(1)
        ]);

        // チャレンジ作成
        $this->createChallengesForAuthorization($authorization);

        return $authorization;
    }

    /**
     * Authorizationのチャレンジ作成
     */
    private function createChallengesForAuthorization(AcmeAuthorization $authorization): void
    {
        $identifier = $authorization->identifier;
        
        // HTTP-01 Challenge
        if ($identifier['type'] === 'dns') {
            AcmeChallenge::create([
                'authorization_id' => $authorization->id,
                'type' => 'http-01',
                'status' => 'pending',
                'token' => $this->generateToken(),
            ]);
        }

        // DNS-01 Challenge
        AcmeChallenge::create([
            'authorization_id' => $authorization->id,
            'type' => 'dns-01',
            'status' => 'pending',
            'token' => $this->generateToken(),
        ]);
    }

    /**
     * チャレンジトークン生成
     */
    private function generateToken(): string
    {
        return base64url_encode(random_bytes(32));
    }

    /**
     * プロバイダー選択
     */
    private function selectProvider(Subscription $subscription, array $identifiers): string
    {
        // サブスクリプションプランに基づくプロバイダー選択
        return match ($subscription->plan_type) {
            'basic' => 'google_certificate_manager', // 無料証明書
            'professional', 'enterprise' => 'gogetssl', // 有料証明書
            default => 'google_certificate_manager'
        };
    }

    /**
     * ドメイン制限検証
     */
    private function validateDomainLimits(Subscription $subscription, array $identifiers): void
    {
        $domainCount = count($identifiers);
        if ($domainCount > $subscription->max_domains) {
            throw new \Exception("Domain limit exceeded: {$domainCount}/{$subscription->max_domains}", 429);
        }

        // 既存の証明書数もチェック
        $existingCertificates = $subscription->certificates()->count();
        if ($existingCertificates + $domainCount > $subscription->max_domains) {
            throw new \Exception("Total domain limit would be exceeded", 429);
        }
    }

    /**
     * CSR検証
     */
    private function validateCsr(string $csr, array $identifiers): void
    {
        try {
            $csrData = base64url_decode($csr);
            $csrResource = openssl_csr_get_subject($csrData);
            
            if (!$csrResource) {
                throw new \Exception('Invalid CSR format', 400);
            }

            // CSR内のドメインとオーダーのドメインが一致するか確認
            $csrCN = $csrResource['CN'] ?? null;
            $orderDomains = array_column($identifiers, 'value');

            if ($csrCN && !in_array($csrCN, $orderDomains)) {
                throw new \Exception('CSR Common Name does not match order identifiers', 400);
            }

        } catch (\Exception $e) {
            throw new \Exception('CSR validation failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * プロバイダーで証明書発行
     */
    private function issueCertificateWithProvider(AcmeOrder $order, string $csr): void
    {
        // ここでバックエンドプロバイダーに証明書発行を依頼
        // 実装は ProcessCertificateValidation ジョブで行う
        
        dispatch(new \App\Jobs\ProcessAcmeCertificateIssuance($order, $csr));
    }
}