<?php

namespace App\Services\CertificateProviders;

use App\Contracts\CertificateProviderInterface;
use App\Services\GoogleCertificateManagerService;
use Illuminate\Support\Facades\Log;

/**
 * Google Certificate Manager Provider Adapter
 * GoogleCertificateManagerServiceを統一インターフェースで利用するためのアダプター
 */
class GoogleCertificateManagerProvider implements CertificateProviderInterface
{
    private GoogleCertificateManagerService $googleService;

    public function __construct(GoogleCertificateManagerService $googleService)
    {
        $this->googleService = $googleService;
    }

    /**
     * Create new SSL certificate
     */
    public function createCertificate(array $domains, array $options = []): array
    {
        try {
            $result = $this->googleService->createManagedCertificate($domains, $options);

            return [
                'success' => true,
                'certificate_id' => $result['certificate_id'],
                'provider' => 'google_certificate_manager',
                'domains' => $domains,
                'status' => $this->mapGoogleStatus($result['state']),
                'provider_data' => $result,
                'validation_required' => $result['state'] !== 'ACTIVE',
                'dns_authorizations' => $result['dns_authorizations'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error('Google Certificate Manager certificate creation failed', [
                'domains' => $domains,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'google_certificate_manager'
            ];
        }
    }

    /**
     * Get certificate status
     */
    public function getCertificateStatus(string $certificateId): array
    {
        try {
            $status = $this->googleService->getCertificateStatus($certificateId);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'google_certificate_manager',
                'status' => $this->mapGoogleStatus($status['state']),
                'is_issued' => $status['is_active'],
                'domains' => $status['domains'],
                'issued_at' => null, // Google doesn't provide exact issuance time
                'expires_at' => $status['expire_time'],
                'provisioning_issue' => $status['provisioning_issue'],
                'dns_authorizations' => $status['dns_authorizations'],
                'provider_data' => $status
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get Google Certificate Manager status', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'google_certificate_manager',
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
            $status = $this->googleService->getCertificateStatus($certificateId);
            $instructions = [];

            // DNS認証の手順を生成
            foreach ($status['dns_authorizations'] as $auth) {
                if (isset($auth['dns_record'])) {
                    $dnsRecord = $auth['dns_record'];
                    $instructions[] = [
                        'type' => 'DNS',
                        'domain' => $auth['domain'],
                        'description' => 'DNS TXT レコードを追加してください',
                        'record_name' => $dnsRecord['name'],
                        'record_type' => $dnsRecord['type'],
                        'record_value' => $dnsRecord['data'],
                        'ttl' => 300,
                        'auth_state' => $auth['state'],
                        'note' => 'このDNSレコードを追加後、Googleが自動的に検証を行います'
                    ];
                }
            }

            return [
                'certificate_id' => $certificateId,
                'provider' => 'google_certificate_manager',
                'dcv_method' => 'dns',
                'instructions' => $instructions,
                'status' => $status['state'],
                'auto_validation' => true,
                'note' => 'Google Certificate Managerは自動的にDNS検証を行います'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get Google Certificate Manager validation instructions', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'google_certificate_manager',
                'error' => $e->getMessage(),
                'instructions' => []
            ];
        }
    }

    /**
     * Download certificate
     * Note: Google Certificate Manager doesn't provide direct certificate download
     * Certificates are automatically managed by Google Cloud
     */
    public function downloadCertificate(string $certificateId): array
    {
        Log::warning('Certificate download not supported for Google Certificate Manager', [
            'certificate_id' => $certificateId
        ]);

        throw new \Exception('Certificate download is not supported for Google Certificate Manager. Certificates are automatically managed by Google Cloud and integrated with Load Balancers.');
    }

    /**
     * Revoke certificate
     */
    public function revokeCertificate(string $certificateId, string $reason = 'unspecified'): array
    {
        try {
            // Google Certificate Managerでは証明書を削除することで失効させる
            $result = $this->googleService->deleteCertificate($certificateId);

            // 関連するDNS認証も削除
            $status = $this->googleService->getCertificateStatus($certificateId);
            foreach ($status['dns_authorizations'] as $auth) {
                try {
                    $this->googleService->deleteDnsAuthorization($auth['id']);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete DNS authorization', [
                        'auth_id' => $auth['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'certificate_id' => $certificateId,
                'provider' => 'google_certificate_manager',
                'success' => $result['success'],
                'revoked_at' => now()->toDateTimeString(),
                'reason' => $reason,
                'note' => 'Certificate and DNS authorizations deleted from Google Certificate Manager',
                'provider_data' => $result
            ];

        } catch (\Exception $e) {
            Log::error('Failed to revoke Google Certificate Manager certificate', [
                'certificate_id' => $certificateId,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return [
                'certificate_id' => $certificateId,
                'provider' => 'google_certificate_manager',
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
        return $this->googleService->testConnection();
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'Google Certificate Manager';
    }

    /**
     * Get supported certificate types
     */
    public function getSupportedCertificateTypes(): array
    {
        return [
            'DV' => 'Domain Validated (Managed)',
            'WILDCARD' => 'Wildcard Certificate (Managed)',
            'MULTI_DOMAIN' => 'Multi-Domain Certificate (Managed)'
        ];
    }

    /**
     * Validate domains
     */
    public function validateDomains(array $domains): array
    {
        $errors = [];
        $warnings = [];

        // Google Certificate Manager specific validations
        if (count($domains) > 100) {
            $errors[] = 'Google Certificate Manager supports maximum 100 domains per certificate';
        }

        foreach ($domains as $index => $domain) {
            // 基本的なドメイン形式チェック
            if (!$this->isValidDomainFormat($domain)) {
                $errors[] = "Domain '{$domain}' has invalid format";
                continue;
            }

            // Google Certificate Manager specific restrictions
            if (strlen($domain) > 253) {
                $errors[] = "Domain '{$domain}' exceeds maximum length of 253 characters";
            }

            // ワイルドカードドメインのチェック
            if (str_starts_with($domain, '*.')) {
                if (substr_count($domain, '.') < 2) {
                    $errors[] = "Wildcard domain '{$domain}' must have at least one subdomain level";
                }
                
                // ワイルドカードは1レベルのみサポート
                if (str_contains($domain, '*.*.')) {
                    $errors[] = "Domain '{$domain}' contains nested wildcards which are not supported";
                }
            }

            // 重複チェック
            if (array_count_values($domains)[$domain] > 1) {
                $warnings[] = "Domain '{$domain}' is duplicated";
            }

            // 国際化ドメイン名のチェック
            if (!mb_check_encoding($domain, 'ASCII')) {
                $warnings[] = "Domain '{$domain}' contains non-ASCII characters. Ensure proper IDN encoding.";
            }
        }

        // ワイルドカードと通常ドメインの混在チェック
        $wildcardDomains = array_filter($domains, fn($d) => str_starts_with($d, '*.'));
        $regularDomains = array_filter($domains, fn($d) => !str_starts_with($d, '*.'));

        if (!empty($wildcardDomains) && !empty($regularDomains)) {
            foreach ($wildcardDomains as $wildcard) {
                $baseDomain = substr($wildcard, 2);
                if (in_array($baseDomain, $regularDomains)) {
                    $warnings[] = "Wildcard domain '{$wildcard}' and regular domain '{$baseDomain}' should not be used together";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'domain_count' => count($domains),
            'has_wildcards' => !empty($wildcardDomains),
            'google_specific_notes' => [
                'auto_renewal' => 'Certificates are automatically renewed by Google',
                'integration' => 'Best used with Google Cloud Load Balancers',
                'validation' => 'DNS validation is performed automatically'
            ]
        ];
    }

    /**
     * Map Google Certificate Manager status to standard status
     */
    private function mapGoogleStatus(string $googleStatus): string
    {
        return match (strtoupper($googleStatus)) {
            'ACTIVE' => 'issued',
            'PENDING' => 'pending_validation',
            'PROVISIONING' => 'processing',
            'RENEWAL_PENDING' => 'processing',
            'FAILED' => 'failed',
            default => 'unknown'
        };
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

    /**
     * Get estimated provisioning time
     */
    public function getEstimatedProvisioningTime(): array
    {
        return [
            'min_minutes' => 15,
            'max_minutes' => 60,
            'average_minutes' => 30,
            'note' => 'Google Certificate Manager typically provisions certificates within 15-60 minutes after DNS validation'
        ];
    }

    /**
     * Get integration recommendations
     */
    public function getIntegrationRecommendations(): array
    {
        return [
            'load_balancer' => 'Use with Google Cloud Load Balancer for automatic certificate attachment',
            'cloud_run' => 'Can be used with Cloud Run services with custom domains',
            'gke' => 'Integrate with Google Kubernetes Engine Ingress',
            'dns_management' => 'Consider using Google Cloud DNS for easier domain validation',
            'monitoring' => 'Use Google Cloud Monitoring for certificate expiration alerts'
        ];
    }
}
