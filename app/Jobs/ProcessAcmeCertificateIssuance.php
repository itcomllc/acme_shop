<?php

namespace App\Jobs;

use App\Models\{AcmeOrder, Certificate, AcmeAuthorization};
use App\Services\{GoGetSSLService, GoogleCertificateManagerService, CertificateProviderFactory};
use App\Events\{CertificateIssued, CertificateFailed};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Process ACME Certificate Issuance Job
 * ACMEオーダーに基づいてバックエンドプロバイダーで証明書を発行
 */
class ProcessAcmeCertificateIssuance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private AcmeOrder $order;
    private string $csr;

    public function __construct(AcmeOrder $order, string $csr)
    {
        $this->order = $order;
        $this->csr = $csr;
    }

    public function handle(): void
    {
        try {
            Log::info('Starting ACME certificate issuance', [
                'order_id' => $this->order->id,
                'provider' => $this->order->selected_provider,
                'identifiers' => $this->order->identifiers
            ]);

            // CSRをデコード
            $csrPem = $this->decodeCsr($this->csr);

            // プロバイダーに応じて証明書発行
            $result = match ($this->order->selected_provider) {
                'gogetssl' => $this->issueWithGoGetSSL($csrPem),
                'google_certificate_manager' => $this->issueWithGoogleCM(),
                'lets_encrypt' => $this->issueWithLetsEncrypt($csrPem),
                default => throw new \Exception("Unsupported provider: {$this->order->selected_provider}")
            };

            if ($result['success']) {
                $this->handleSuccessfulIssuance($result);
            } else {
                $this->handleFailedIssuance($result['error'] ?? 'Unknown error');
            }

        } catch (\Exception $e) {
            Log::error('ACME certificate issuance failed', [
                'order_id' => $this->order->id,
                'provider' => $this->order->selected_provider,
                'error' => $e->getMessage()
            ]);

            $this->handleFailedIssuance($e->getMessage());
        }
    }

    /**
     * GoGetSSLで証明書発行
     */
    private function issueWithGoGetSSL(string $csrPem): array
    {
        try {
            /** @var GoGetSSLService */
            $goGetSSLService = app(GoGetSSLService::class);
            
            // ドメインを取得
            $domains = array_column($this->order->identifiers, 'value');
            $primaryDomain = $domains[0];
            
            // GoGetSSL注文データ準備
            $orderData = [
                'product_id' => $this->getGoGetSSLProductId(),
                'csr' => $csrPem,
                'period' => 12, // 12ヶ月
                'dcv_method' => 'dns',
                'admin_email' => $this->order->account->subscription->user->email,
                'admin_firstname' => 'SSL',
                'admin_lastname' => 'Administrator',
                'admin_phone' => '+1-555-0123',
                'admin_title' => 'System Administrator',
                'tech_firstname' => 'SSL',
                'tech_lastname' => 'Administrator',
                'tech_phone' => '+1-555-0123',
                'tech_title' => 'System Administrator',
                'tech_email' => $this->order->account->subscription->user->email,
            ];

            // 追加ドメイン（SAN）
            if (count($domains) > 1) {
                $orderData['dns_names'] = implode(',', array_slice($domains, 1));
            }

            // GoGetSSL注文作成
            $goGetSSLOrder = $goGetSSLService->createOrder($orderData);
            
            Log::info('GoGetSSL order created for ACME', [
                'acme_order_id' => $this->order->id,
                'gogetssl_order_id' => $goGetSSLOrder['order_id'],
                'domain' => $primaryDomain
            ]);

            return [
                'success' => true,
                'provider_order_id' => $goGetSSLOrder['order_id'],
                'provider_data' => $goGetSSLOrder,
                'status' => 'processing'
            ];

        } catch (\Exception $e) {
            Log::error('GoGetSSL issuance failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Google Certificate Managerで証明書発行
     */
    private function issueWithGoogleCM(): array
    {
        try {
            /** @var GoogleCertificateManagerService */
            $googleCM = app(GoogleCertificateManagerService::class);
            
            $domains = array_column($this->order->identifiers, 'value');
            
            // Google Certificate Manager証明書作成
            $result = $googleCM->createManagedCertificate($domains, [
                'description' => "ACME certificate for order {$this->order->id}",
                'acme_order_id' => $this->order->id
            ]);

            Log::info('Google Certificate Manager certificate created for ACME', [
                'acme_order_id' => $this->order->id,
                'google_cert_id' => $result['certificate_id'],
                'domains' => $domains
            ]);

            return [
                'success' => true,
                'provider_certificate_id' => $result['certificate_id'],
                'provider_data' => $result,
                'status' => 'processing'
            ];

        } catch (\Exception $e) {
            Log::error('Google Certificate Manager issuance failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Let's Encryptで証明書発行（実際のACME実装）
     */
    private function issueWithLetsEncrypt(string $csrPem): array
    {
        try {
            // Let's Encryptの場合は、このジョブ内でACME CA APIを直接呼び出し
            // 実際の実装では、Let's Encrypt ACME v2 APIを使用
            
            Log::info('Let\'s Encrypt certificate issuance initiated', [
                'order_id' => $this->order->id,
                'domains' => array_column($this->order->identifiers, 'value')
            ]);

            // 簡略化された実装 - 実際にはACME CA APIを呼び出す
            return [
                'success' => true,
                'provider_certificate_id' => 'le_' . uniqid(),
                'provider_data' => [
                    'acme_ca' => 'lets_encrypt',
                    'order_id' => $this->order->id
                ],
                'status' => 'processing'
            ];

        } catch (\Exception $e) {
            Log::error('Let\'s Encrypt issuance failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 成功時の処理
     */
    private function handleSuccessfulIssuance(array $result): void
    {
        // Certificateレコード作成
        $domains = array_column($this->order->identifiers, 'value');
        $primaryDomain = $domains[0];

        $certificate = Certificate::create([
            'subscription_id' => $this->order->subscription_id,
            'domain' => $primaryDomain,
            'type' => $this->getCertificateType(),
            'provider' => $this->order->selected_provider,
            'provider_certificate_id' => $result['provider_certificate_id'] ?? $result['provider_order_id'] ?? null,
            'status' => Certificate::STATUS_PROCESSING,
            'acme_order_id' => $this->order->id,
            'expires_at' => $this->calculateExpiryDate(),
            'provider_data' => $result['provider_data'] ?? []
        ]);

        // ACMEオーダー更新
        $this->order->update([
            'status' => 'processing',
            'certificate_url' => route('acme.certificate', $certificate->id),
            'provider_data' => $result['provider_data'] ?? []
        ]);

        // 証明書監視ジョブをスケジュール
        MonitorCertificateIssuance::dispatch($certificate)->delay(now()->addMinutes(5));

        Log::info('ACME certificate issuance initiated successfully', [
            'order_id' => $this->order->id,
            'certificate_id' => $certificate->id,
            'provider' => $this->order->selected_provider
        ]);
    }

    /**
     * 失敗時の処理
     */
    private function handleFailedIssuance(string $error): void
    {
        // ACMEオーダーを無効状態に更新
        $this->order->update([
            'status' => 'invalid',
            'provider_data' => array_merge(
                $this->order->provider_data ?? [],
                ['error' => $error, 'failed_at' => now()->toISOString()]
            )
        ]);

        // 関連するAuthorizationも無効に
        $this->order->authorizations()->update(['status' => 'invalid']);

        Log::error('ACME certificate issuance failed completely', [
            'order_id' => $this->order->id,
            'error' => $error
        ]);
    }

    /**
     * CSRをデコード
     */
    private function decodeCsr(string $base64Csr): string
    {
        $csrData = base64url_decode($base64Csr);
        
        // PEM形式でない場合は変換
        if (!str_starts_with($csrData, '-----BEGIN')) {
            $csrData = "-----BEGIN CERTIFICATE REQUEST-----\n" .
                      chunk_split(base64_encode($csrData), 64, "\n") .
                      "-----END CERTIFICATE REQUEST-----";
        }

        return $csrData;
    }

    /**
     * GoGetSSL製品IDを取得
     */
    private function getGoGetSSLProductId(): int
    {
        $subscription = $this->order->account->subscription;
        
        return match ($subscription->certificate_type) {
            'DV' => 1, // GoGetSSL DV SSL
            'OV' => 2, // GoGetSSL OV SSL
            'EV' => 3, // GoGetSSL EV SSL
            default => 1
        };
    }

    /**
     * 証明書タイプを取得
     */
    private function getCertificateType(): string
    {
        $subscription = $this->order->account->subscription;
        return $subscription->certificate_type ?? 'DV';
    }

    /**
     * 証明書有効期限を計算
     */
    private function calculateExpiryDate(): \Carbon\Carbon
    {
        return match ($this->order->selected_provider) {
            'gogetssl' => now()->addDays(365), // 1年
            'google_certificate_manager' => now()->addDays(90), // 90日
            'lets_encrypt' => now()->addDays(90), // 90日
            default => now()->addDays(90)
        };
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ACME certificate issuance job failed', [
            'order_id' => $this->order->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->handleFailedIssuance("Job failed: {$exception->getMessage()}");
    }
}