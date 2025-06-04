<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeAccount, Certificate, AcmeOrder};
use Illuminate\Http\{Request, JsonResponse, Response};
use Illuminate\Support\Facades\Log;
use App\Services\{AcmeJwsService, GoGetSSLService, GoogleCertificateManagerService};

/**
 * ACME Certificate Controller
 * RFC 8555 Section 7.4.2 - Certificate Management
 */
class AcmeCertificateController extends Controller
{
    public function __construct(
        private readonly AcmeJwsService $jwsService
    ) {}

    /**
     * Get certificate (RFC 8555 Section 7.4.2)
     */
    public function getCertificate(Request $request, Certificate $certificate): Response
    {
        try {
            // JWS検証・アカウント認証
            $jws = $this->jwsService->verifyJws($request->getContent());
            $account = $this->getAccountFromJws($jws);
            
            // 証明書所有権確認
            if (!$this->verifyCertificateAccess($certificate, $account)) {
                return response([
                    'type' => 'urn:ietf:params:acme:error:unauthorized',
                    'detail' => 'Certificate does not belong to this account'
                ], 403)->header('Content-Type', 'application/problem+json');
            }

            // 証明書が発行済みか確認
            if ($certificate->status !== Certificate::STATUS_ISSUED) {
                return response([
                    'type' => 'urn:ietf:params:acme:error:certificateNotYetAvailable',
                    'detail' => 'Certificate is not yet available'
                ], 404)->header('Content-Type', 'application/problem+json');
            }

            // 証明書データを取得
            $certificateData = $this->getCertificateData($certificate);
            
            if (!$certificateData) {
                return response([
                    'type' => 'urn:ietf:params:acme:error:serverInternal',
                    'detail' => 'Certificate data not available'
                ], 500)->header('Content-Type', 'application/problem+json');
            }

            Log::info('ACME certificate downloaded', [
                'certificate_id' => $certificate->id,
                'domain' => $certificate->domain,
                'account_id' => $account->id
            ]);

            // Accept ヘッダーに基づいてフォーマットを決定
            $acceptHeader = $request->header('Accept', 'application/pem-certificate-chain');
            $contentType = $this->getContentTypeFromAccept($acceptHeader);
            $certificateContent = $this->formatCertificate($certificateData, $contentType);

            return response($certificateContent, 200)
                ->header('Content-Type', $contentType);

        } catch (\Exception $e) {
            Log::error('ACME certificate retrieval failed', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => 'Failed to retrieve certificate'
            ], 500)->header('Content-Type', 'application/problem+json');
        }
    }

    /**
     * Revoke certificate (RFC 8555 Section 7.6)
     */
    public function revokeCertificate(Request $request): JsonResponse
    {
        try {
            // JWS検証・アカウント認証
            $jws = $this->jwsService->verifyJws($request->getContent());
            $account = $this->getAccountFromJws($jws);
            
            $payload = $jws['payload'];
            $certificateDer = $payload['certificate'] ?? null;
            $reason = $payload['reason'] ?? 0; // RFC 5280 reason codes

            if (!$certificateDer) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:malformed',
                    'detail' => 'Certificate is required'
                ], 400);
            }

            // 証明書を検索
            $certificate = $this->findCertificateByDer($certificateDer);
            
            if (!$certificate) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:certificateNotFound',
                    'detail' => 'Certificate not found'
                ], 404);
            }

            // 失効権限確認
            if (!$this->canRevokeCertificate($certificate, $account)) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:unauthorized',
                    'detail' => 'Not authorized to revoke this certificate'
                ], 403);
            }

            // 証明書失効処理
            $this->performCertificateRevocation($certificate, $reason);

            Log::info('ACME certificate revoked', [
                'certificate_id' => $certificate->id,
                'domain' => $certificate->domain,
                'account_id' => $account->id,
                'reason' => $reason
            ]);

            return response()->json([], 200);

        } catch (\Exception $e) {
            Log::error('ACME certificate revocation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => 'Failed to revoke certificate'
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
     * 証明書アクセス権限確認
     */
    private function verifyCertificateAccess(Certificate $certificate, AcmeAccount $account): bool
    {
        // ACME経由で発行された証明書の場合
        if ($certificate->acme_order_id) {
            $acmeOrder = AcmeOrder::find($certificate->acme_order_id);
            return $acmeOrder && $acmeOrder->account_id === $account->id;
        }

        // サブスクリプション経由の場合
        return $certificate->subscription_id === $account->subscription_id;
    }

    /**
     * 証明書データを取得
     */
    private function getCertificateData(Certificate $certificate): ?array
    {
        $certificateData = $certificate->certificate_data;
        
        if ($certificateData && isset($certificateData['certificate'])) {
            return $certificateData;
        }

        // プロバイダーから直接取得を試行
        try {
            return $this->fetchCertificateFromProvider($certificate);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch certificate from provider', [
                'certificate_id' => $certificate->id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * プロバイダーから証明書データを取得
     */
    private function fetchCertificateFromProvider(Certificate $certificate): ?array
    {
        switch ($certificate->provider) {
            case Certificate::PROVIDER_GOGETSSL:
                return $this->fetchFromGoGetSSL($certificate);
                
            case Certificate::PROVIDER_GOOGLE_CM:
                // Google Certificate Managerは直接ダウンロード不可
                throw new \Exception('Google Certificate Manager does not support direct certificate download');
                
            case Certificate::PROVIDER_LETS_ENCRYPT:
                return $this->fetchFromLetsEncrypt($certificate);
                
            default:
                throw new \Exception("Unsupported provider: {$certificate->provider}");
        }
    }

    /**
     * GoGetSSLから証明書を取得
     */
    private function fetchFromGoGetSSL(Certificate $certificate): array
    {
        /** @var GoGetSSLService */
        $goGetSSLService = app(GoGetSSLService::class);
        
        $orderId = $certificate->provider_certificate_id ?? $certificate->gogetssl_order_id;
        
        if (!$orderId) {
            throw new \Exception('GoGetSSL order ID not found');
        }

        return $goGetSSLService->downloadCertificate((int) $orderId);
    }

    /**
     * Let's Encryptから証明書を取得
     */
    private function fetchFromLetsEncrypt(Certificate $certificate): array
    {
        // Let's Encryptの場合、通常はACME CA APIから取得
        // 簡略化実装
        throw new \Exception('Let\'s Encrypt certificate download not implemented');
    }

    /**
     * Accept ヘッダーからContent-Typeを決定
     */
    private function getContentTypeFromAccept(string $acceptHeader): string
    {
        if (str_contains($acceptHeader, 'application/pem-certificate-chain')) {
            return 'application/pem-certificate-chain';
        }
        
        if (str_contains($acceptHeader, 'application/pkix-cert')) {
            return 'application/pkix-cert';
        }
        
        // デフォルトはPEM形式
        return 'application/pem-certificate-chain';
    }

    /**
     * 証明書をフォーマット
     */
    private function formatCertificate(array $certificateData, string $contentType): string
    {
        switch ($contentType) {
            case 'application/pem-certificate-chain':
                // PEM形式（証明書 + CA bundle）
                $certificate = $certificateData['certificate'] ?? '';
                $caBundle = $certificateData['ca_bundle'] ?? '';
                return $certificate . "\n" . $caBundle;
                
            case 'application/pkix-cert':
                // DER形式（バイナリ）
                $pemCert = $certificateData['certificate'] ?? '';
                return $this->pemToDer($pemCert);
                
            default:
                return $certificateData['certificate'] ?? '';
        }
    }

    /**
     * PEMをDER形式に変換
     */
    private function pemToDer(string $pemCertificate): string
    {
        $pemData = str_replace([
            '-----BEGIN CERTIFICATE-----',
            '-----END CERTIFICATE-----',
            "\r\n", "\r", "\n", " "
        ], '', $pemCertificate);
        
        return base64_decode($pemData);
    }

    /**
     * DER形式から証明書を検索
     */
    private function findCertificateByDer(string $certificateDer): ?Certificate
    {
        // DER形式をPEMに変換して検索
        $pemCertificate = "-----BEGIN CERTIFICATE-----\n" .
                         chunk_split(base64_encode($certificateDer), 64, "\n") .
                         "-----END CERTIFICATE-----";
        
        // 証明書のハッシュを計算して検索
        $certHash = hash('sha256', $certificateDer);
        
        // 証明書データにハッシュが含まれている場合を検索
        return Certificate::whereJsonContains('certificate_data->cert_hash', $certHash)
                         ->orWhere(function ($query) use ($pemCertificate) {
                             $query->whereJsonContains('certificate_data->certificate', $pemCertificate);
                         })
                         ->first();
    }

    /**
     * 証明書失効権限確認
     */
    private function canRevokeCertificate(Certificate $certificate, AcmeAccount $account): bool
    {
        // 証明書の所有者か確認
        if ($this->verifyCertificateAccess($certificate, $account)) {
            return true;
        }

        // 証明書の公開鍵とアカウントの公開鍵が一致するか確認（Key Authorization）
        // 実装簡略化のため、所有者チェックのみ
        return false;
    }

    /**
     * 証明書失効実行
     */
    private function performCertificateRevocation(Certificate $certificate, int $reason): void
    {
        // 失効理由のマッピング（RFC 5280）
        $reasonMapping = [
            0 => 'unspecified',
            1 => 'keyCompromise',
            2 => 'cACompromise',
            3 => 'affiliationChanged',
            4 => 'superseded',
            5 => 'cessationOfOperation',
            6 => 'certificateHold',
            8 => 'removeFromCRL',
            9 => 'privilegeWithdrawn',
            10 => 'aACompromise'
        ];

        $reasonText = $reasonMapping[$reason] ?? 'unspecified';

        // 証明書を失効状態に更新
        $certificate->update([
            'status' => Certificate::STATUS_REVOKED,
            'revoked_at' => now(),
            'revocation_reason' => $reasonText
        ]);

        // プロバイダー側でも失効処理
        try {
            $this->revokeAtProvider($certificate, $reasonText);
        } catch (\Exception $e) {
            Log::warning('Provider revocation failed', [
                'certificate_id' => $certificate->id,
                'provider' => $certificate->provider,
                'error' => $e->getMessage()
            ]);
        }

        // 関連するACMEオーダーも更新
        if ($certificate->acme_order_id) {
            $acmeOrder = AcmeOrder::find($certificate->acme_order_id);
            if ($acmeOrder) {
                $acmeOrder->update(['status' => 'invalid']);
            }
        }
    }

    /**
     * プロバイダー側で失効処理
     */
    private function revokeAtProvider(Certificate $certificate, string $reason): void
    {
        switch ($certificate->provider) {
            case Certificate::PROVIDER_GOGETSSL:
                if ($certificate->provider_certificate_id || $certificate->gogetssl_order_id) {
                    /** @var GoGetSSLService */
                    $goGetSSLService = app(GoGetSSLService::class);
                    $orderId = $certificate->provider_certificate_id ?? $certificate->gogetssl_order_id;
                    $goGetSSLService->revokeCertificate((int) $orderId, $reason);
                }
                break;
                
            case Certificate::PROVIDER_GOOGLE_CM:
                if ($certificate->provider_certificate_id) {
                    /** @var GoogleCertificateManagerService */
                    $googleCM = app(GoogleCertificateManagerService::class);
                    $googleCM->deleteCertificate($certificate->provider_certificate_id);
                }
                break;
                
            case Certificate::PROVIDER_LETS_ENCRYPT:
                // Let's Encrypt ACME revocation API を呼び出し
                // 実装簡略化
                break;
        }
    }
}
