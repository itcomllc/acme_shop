<?php

namespace App\Services\CertificateProviders;

use App\Contracts\CertificateProviderInterface;
use App\Services\GoGetSSLService;
use Illuminate\Support\Facades\Log;

/**
 * GoGetSSL Provider Adapter
 * GoGetSSLServiceを統一インターフェースで利用するためのアダプター
 */
class GoGetSSLProvider implements CertificateProviderInterface
{
    private GoGetSSLService $goGetSSLService;

    public function __construct(GoGetSSLService $goGetSSLService)
    {
        $this->goGetSSLService = $goGetSSLService;
    }

    /**
     * Create new SSL certificate
     */
    public function createCertificate(array $domains, array $options = []): array
    {
        try {
            // 必須パラメータの設定
            $orderData = [
                'product_id' => $options['product_id'] ?? 1, // デフォルトはDV SSL
                'period' => $options['period'] ?? 12, // 12ヶ月
                'csr' => $options['csr'] ?? $this->generateCSR($domains[0]),
                'dcv_method' => $options['dcv_method'] ?? 'dns',
                
                // 管理者情報
                'admin_firstname' => $options['admin_firstname'] ?? 'SSL',
                'admin_lastname' => $options['admin_lastname'] ?? 'Administrator',
                'admin_phone' => $options['admin_phone'] ?? '+1-555-0123',
                'admin_title' => $options['admin_title'] ?? 'System Administrator',
                'admin_email' => $options['admin_email'] ?? $options['contact_email'] ?? 'admin@example.com',
                
                // 技術者情報（管理者と同じにする）
                'tech_firstname' => $options['admin_firstname'] ?? 'SSL',
                'tech_lastname' => $options['admin_lastname'] ?? 'Administrator', 
                'tech_phone' => $options['admin_phone'] ?? '+1-555-0123',
                'tech_title' => $options['admin_title'] ?? 'System Administrator',
                'tech_email' => $options['admin_email'] ?? $options['contact_email'] ?? 'admin@example.com'
            ];

            // 追加ドメイン（SAN）の処理
            if (count($domains) > 1) {
                $orderData['dns_names'] = implode(',', array_slice($domains, 1));
            }

            // テストモード
            if ($options['test_mode'] ?? false) {
                $orderData['test'] = true;
            }

            $result = $this->goGetSSLService->createOrder($orderData);

            return [
                'success' => true,
                'certificate_id' => (string) $result['order_id'],
                'provider' => 'gogetssl',
                'domains' => $domains,
                'status' => 'pending_validation',
                'provider_data' => $result,
                'validation_required' => true
            ];

        } catch (\Exception $e) {
            Log::error('GoGetSSL certificate creation failed', [
                'domains' => $domains,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'gogetssl'
            ];
        }
    }

    /**
     * Get certificate status
     */
    public function getCertificateStatus(string $certificateId): array
    {
        try {
            $status = $this->goGetSSLService->getOrderStatus((int) $certificateId);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'gogetssl',
                'status' => $this->mapGoGetSSLStatus($status['status'] ?? 'unknown'),
                'is_issued' => ($status['status'] ?? '') === 'issued',
                'domains' => $this->extractDomainsFromStatus($status),
                'issued_at' => $status['valid_from'] ?? null,
                'expires_at' => $status['valid_till'] ?? null,
                'provider_data' => $status
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get GoGetSSL certificate status', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'gogetssl',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get validation instructions
     */
    public function getValidationInstructions(string $certificateId): array
    {
        try {
            $status = $this->goGetSSLService->getOrderStatus((int) $certificateId);
            $instructions = [];

            // DCVメソッドに基づいて検証手順を生成
            $dcvMethod = $status['dcv_method'] ?? 'email';
            $domains = $this->extractDomainsFromStatus($status);

            switch ($dcvMethod) {
                case 'dns':
                    foreach ($domains as $domain) {
                        $instructions[] = [
                            'type' => 'DNS',
                            'domain' => $domain,
                            'description' => 'DNS TXT レコードを追加してください',
                            'record_name' => "_dv.{$domain}",
                            'record_value' => $status['validation_token'] ?? 'Contact support for DNS validation token',
                            'ttl' => 300
                        ];
                    }
                    break;

                case 'http':
                case 'file':
                    foreach ($domains as $domain) {
                        $instructions[] = [
                            'type' => 'HTTP',
                            'domain' => $domain,
                            'description' => 'ウェブサーバーに検証ファイルを配置してください',
                            'file_path' => '/.well-known/pki-validation/fileauth.txt',
                            'file_content' => $status['validation_content'] ?? 'Contact support for HTTP validation content',
                            'verification_url' => "http://{$domain}/.well-known/pki-validation/fileauth.txt"
                        ];
                    }
                    break;

                case 'email':
                    $approverEmails = [];
                    foreach ($domains as $domain) {
                        try {
                            $domainEmails = $this->goGetSSLService->getApprovalEmails($domain);
                            $approverEmails[$domain] = $domainEmails;
                        } catch (\Exception $e) {
                            $approverEmails[$domain] = ["admin@{$domain}", "webmaster@{$domain}"];
                        }
                    }

                    $instructions[] = [
                        'type' => 'EMAIL',
                        'description' => '承認メールを確認してリンクをクリックしてください',
                        'approver_emails' => $approverEmails,
                        'note' => '各ドメインに承認メールが送信されます'
                    ];
                    break;
            }

            return [
                'certificate_id' => $certificateId,
                'provider' => 'gogetssl',
                'dcv_method' => $dcvMethod,
                'instructions' => $instructions,
                'status' => $status['status'] ?? 'unknown'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get GoGetSSL validation instructions', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'gogetssl',
                'error' => $e->getMessage(),
                'instructions' => []
            ];
        }
    }

    /**
     * Download certificate
     */
    public function downloadCertificate(string $certificateId): array
    {
        try {
            $certData = $this->goGetSSLService->downloadCertificate((int) $certificateId);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'gogetssl',
                'certificate' => $certData['certificate'],
                'ca_bundle' => $certData['ca_bundle'] ?? '',
                'full_chain' => $certData['certificate'] . "\n" . ($certData['ca_bundle'] ?? ''),
                'valid_from' => $certData['valid_from'] ?? null,
                'valid_till' => $certData['valid_till'] ?? null,
                'format' => 'pem'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to download GoGetSSL certificate', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Certificate download failed: ' . $e->getMessage());
        }
    }

    /**
     * Revoke certificate
     */
    public function revokeCertificate(string $certificateId, string $reason = 'unspecified'): array
    {
        try {
            $result = $this->goGetSSLService->revokeCertificate((int) $certificateId, $reason);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'gogetssl',
                'success' => $result['success'] ?? false,
                'revoked_at' => now()->toDateTimeString(),
                'reason' => $reason,
                'provider_data' => $result
            ];

        } catch (\Exception $e) {
            Log::error('Failed to revoke GoGetSSL certificate', [
                'certificate_id' => $certificateId,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'gogetssl',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        return $this->goGetSSLService->testConnection();
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'GoGetSSL';
    }

    /**
     * Get supported certificate types
     */
    public function getSupportedCertificateTypes(): array
    {
        return [
            'DV' => 'Domain Validated',
            'OV' => 'Organization Validated',
            'EV' => 'Extended Validation',
            'WILDCARD' => 'Wildcard Certificate',
            'MULTI_DOMAIN' => 'Multi-Domain (SAN)'
        ];
    }

    /**
     * Validate domains
     */
    public function validateDomains(array $domains): array
    {
        $errors = [];
        $warnings = [];

        foreach ($domains as $index => $domain) {
            // 基本的なドメイン形式チェック
            if (!$this->isValidDomainFormat($domain)) {
                $errors[] = "Domain '{$domain}' has invalid format";
                continue;
            }

            // ワイルドカードドメインのチェック
            if (str_starts_with($domain, '*.')) {
                if (substr_count($domain, '.') < 2) {
                    $errors[] = "Wildcard domain '{$domain}' must have at least one subdomain level";
                }
            }

            // 重複チェック
            if (array_count_values($domains)[$domain] > 1) {
                $warnings[] = "Domain '{$domain}' is duplicated";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'domain_count' => count($domains),
            'has_wildcards' => count(array_filter($domains, fn($d) => str_starts_with($d, '*.'))) > 0
        ];
    }

    /**
     * Generate CSR for domain
     */
    private function generateCSR(string $domain): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $dn = [
            'CN' => $domain,
            'O' => 'SSL SaaS Platform',
            'C' => 'US'
        ];

        $csr = openssl_csr_new($dn, $privateKey);
        openssl_csr_export($csr, $csrOut);

        return $csrOut;
    }

    /**
     * Map GoGetSSL status to standard status
     */
    private function mapGoGetSSLStatus(string $goGetSSLStatus): string
    {
        return match (strtolower($goGetSSLStatus)) {
            'active', 'issued' => 'issued',
            'pending', 'pending_validation' => 'pending_validation',
            'processing' => 'processing',
            'expired' => 'expired',
            'cancelled', 'canceled' => 'cancelled',
            'rejected' => 'failed',
            default => 'unknown'
        };
    }

    /**
     * Extract domains from GoGetSSL status response
     */
    private function extractDomainsFromStatus(array $status): array
    {
        $domains = [];

        // メインドメイン
        if (!empty($status['domain'])) {
            $domains[] = $status['domain'];
        }

        // SAN domains
        if (!empty($status['san']) && is_array($status['san'])) {
            foreach ($status['san'] as $san) {
                if (!empty($san['san_name'])) {
                    $domains[] = $san['san_name'];
                }
            }
        }

        // domains フィールドから
        if (!empty($status['domains'])) {
            $additional = array_map('trim', explode(',', $status['domains']));
            $domains = array_merge($domains, $additional);
        }

        return array_unique(array_filter($domains));
    }

    /**
     * Check if domain format is valid
     */
    private function isValidDomainFormat(string $domain): bool
    {
        // ワイルドカードドメインの場合
        if (str_starts_with($domain, '*.')) {
            $domain = substr($domain, 2);
        }

        // 基本的なドメイン形式チェック
        return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $domain);
    }
}
