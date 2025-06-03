<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\CertificateManager;
use Google\Service\CertificateManager\Certificate;
use Google\Service\CertificateManager\ManagedCertificate;
use Google\Service\CertificateManager\DnsAuthorization;
use Illuminate\Support\Facades\{Log, Cache};

/**
 * Google Certificate Manager Integration Service
 */
class GoogleCertificateManagerService
{
    private GoogleClient $googleClient;
    private CertificateManager $certificateManager;
    private string $projectId;
    private string $location;

    public function __construct()
    {
        $this->projectId = config('services.google.project_id') ?? '__GOOGLE_PROJECT_ID__';
        $this->location = config('services.google.certificate_manager.location', 'global');
        
        if (empty($this->projectId)) {
            throw new \InvalidArgumentException('Google project ID is required');
        }

        $this->initializeGoogleClient();
    }

    /**
     * Initialize Google Client with proper authentication
     */
    private function initializeGoogleClient(): void
    {
        $this->googleClient = new GoogleClient();
        
        // Service Account認証
        $keyFilePath = config('services.google.key_file_path');
        if ($keyFilePath && file_exists($keyFilePath)) {
            $this->googleClient->setAuthConfig($keyFilePath);
        } else {
            // JSON文字列からの認証
            $keyData = config('services.google.key_data') ?? '{installed: false}';
            if ($keyData) {
                $this->googleClient->setAuthConfig(json_decode($keyData, true));
            } else {
                throw new \InvalidArgumentException('Google credentials not configured');
            }
        }

        $this->googleClient->setScopes([
            'https://www.googleapis.com/auth/cloud-platform'
        ]);

        $this->certificateManager = new CertificateManager($this->googleClient);
    }

    /**
     * Create managed SSL certificate
     */
    public function createManagedCertificate(array $domains, array $options = []): array
    {
        try {
            $certificateId = $options['certificate_id'] ?? $this->generateCertificateId($domains[0]);
            
            // DNS認証の作成
            $dnsAuthorizations = [];
            foreach ($domains as $domain) {
                $dnsAuth = $this->createDnsAuthorization($domain);
                $dnsAuthorizations[] = $dnsAuth['name'];
            }

            // Managed Certificate作成
            $managedCert = new ManagedCertificate();
            $managedCert->setDomains($domains);
            $managedCert->setDnsAuthorizations($dnsAuthorizations);

            $certificate = new Certificate();
            $certificate->setManaged($managedCert);
            $certificate->setDescription($options['description'] ?? "SSL certificate for " . implode(', ', $domains));

            $parent = "projects/{$this->projectId}/locations/{$this->location}";
            
            /** @var \Google\Service\CertificateManager\Operation */
            $operation = $this->certificateManager->projects_locations_certificates->create(
                $parent,
                $certificate,
                ['certificateId' => $certificateId]
            );

            // オペレーションの監視
            $this->waitForOperation($operation->getName());

            // 作成された証明書を取得
            $createdCert = $this->getCertificate($certificateId);

            Log::info('Google managed certificate created', [
                'certificate_id' => $certificateId,
                'domains' => $domains,
                'status' => $createdCert['state'] ?? 'unknown'
            ]);

            return [
                'certificate_id' => $certificateId,
                'name' => $createdCert['name'],
                'domains' => $domains,
                'state' => $createdCert['state'],
                'dns_authorizations' => $dnsAuthorizations,
                'operation_name' => $operation->getName()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create Google managed certificate', [
                'domains' => $domains,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to create managed certificate: ' . $e->getMessage());
        }
    }

    /**
     * Create DNS authorization for domain
     */
    public function createDnsAuthorization(string $domain): array
    {
        try {
            $authId = $this->generateAuthorizationId($domain);
            
            $dnsAuth = new DnsAuthorization();
            $dnsAuth->setDomain($domain);
            $dnsAuth->setDescription("DNS authorization for {$domain}");

            $parent = "projects/{$this->projectId}/locations/{$this->location}";
            
            /** @var \Google\Service\CertificateManager\Operation */
            $operation = $this->certificateManager->projects_locations_dnsAuthorizations->create(
                $parent,
                $dnsAuth,
                ['dnsAuthorizationId' => $authId]
            );

            // オペレーション完了を待機
            $this->waitForOperation($operation->getName());

            // 作成されたDNS認証を取得
            $createdAuth = $this->getDnsAuthorization($authId);

            Log::info('Google DNS authorization created', [
                'domain' => $domain,
                'authorization_id' => $authId,
                'dns_record' => $createdAuth['dnsResourceRecord'] ?? null
            ]);

            return [
                'authorization_id' => $authId,
                'name' => $createdAuth['name'],
                'domain' => $domain,
                'dns_record' => $createdAuth['dnsResourceRecord'] ?? null,
                'state' => $createdAuth['state'] ?? 'unknown'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create DNS authorization', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to create DNS authorization: ' . $e->getMessage());
        }
    }

    /**
     * Get certificate details
     */
    public function getCertificate(string $certificateId): array
    {
        try {
            $name = "projects/{$this->projectId}/locations/{$this->location}/certificates/{$certificateId}";
            
            /** @var ProjectsLocationsCertificates */
            $certificate = $this->certificateManager->projects_locations_certificates->get($name);

            $result = [
                'name' => $certificate->getName(),
                'description' => $certificate->getDescription(),
                'state' => $certificate->getState(),
                'create_time' => $certificate->getCreateTime(),
                'update_time' => $certificate->getUpdateTime(),
                'expire_time' => $certificate->getExpireTime(),
                'subject_alternative_names' => $certificate->getSubjectAlternativeNames(),
                'managed' => []
            ];

            // Managed certificate details
            if ($managed = $certificate->getManaged()) {
                $result['managed'] = [
                    'domains' => $managed->getDomains(),
                    'dns_authorizations' => $managed->getDnsAuthorizations(),
                    'state' => $managed->getState(),
                    'provisioning_issue' => $managed->getProvisioningIssue()
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to get certificate', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to get certificate: ' . $e->getMessage());
        }
    }

    /**
     * Get DNS authorization details
     */
    public function getDnsAuthorization(string $authId): array
    {
        try {
            $name = "projects/{$this->projectId}/locations/{$this->location}/dnsAuthorizations/{$authId}";
            
            /** @var ProjectsLocationsDnsAuthorizations */
            $auth = $this->certificateManager->projects_locations_dnsAuthorizations->get($name);

            $result = [
                'name' => $auth->getName(),
                'description' => $auth->getDescription(),
                'domain' => $auth->getDomain(),
                'state' => $auth->getState(),
                'create_time' => $auth->getCreateTime(),
                'update_time' => $auth->getUpdateTime()
            ];

            // DNS resource record
            if ($dnsRecord = $auth->getDnsResourceRecord()) {
                $result['dns_resource_record'] = [
                    'name' => $dnsRecord->getName(),
                    'type' => $dnsRecord->getType(),
                    'data' => $dnsRecord->getData()
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to get DNS authorization', [
                'authorization_id' => $authId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to get DNS authorization: ' . $e->getMessage());
        }
    }

    /**
     * List all certificates
     */
    public function listCertificates(array $options = []): array
    {
        try {
            $parent = "projects/{$this->projectId}/locations/{$this->location}";
            $params = [];
            
            if (isset($options['page_size'])) {
                $params['pageSize'] = $options['page_size'];
            }
            
            if (isset($options['page_token'])) {
                $params['pageToken'] = $options['page_token'];
            }

            /** @var \Google\Service\CertificateManager\ListCertificatesResponse */
            $response = $this->certificateManager->projects_locations_certificates->listProjectsLocationsCertificates(
                $parent,
                $params
            );

            $certificates = [];
            foreach ($response->getCertificates() as $cert) {
                $certificates[] = [
                    'name' => $cert->getName(),
                    'description' => $cert->getDescription(),
                    'state' => $cert->getState(),
                    'create_time' => $cert->getCreateTime(),
                    'expire_time' => $cert->getExpireTime(),
                    'subject_alternative_names' => $cert->getSubjectAlternativeNames()
                ];
            }

            return [
                'certificates' => $certificates,
                'next_page_token' => $response->getNextPageToken()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to list certificates', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to list certificates: ' . $e->getMessage());
        }
    }

    /**
     * Delete certificate
     */
    public function deleteCertificate(string $certificateId): array
    {
        try {
            $name = "projects/{$this->projectId}/locations/{$this->location}/certificates/{$certificateId}";
            
            /** @var \Google\Service\CertificateManager\Operation */
            $operation = $this->certificateManager->projects_locations_certificates->delete($name);

            // オペレーション完了を待機
            $this->waitForOperation($operation->getName());

            Log::info('Google certificate deleted', [
                'certificate_id' => $certificateId
            ]);

            return [
                'success' => true,
                'operation_name' => $operation->getName()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to delete certificate', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to delete certificate: ' . $e->getMessage());
        }
    }

    /**
     * Delete DNS authorization
     */
    public function deleteDnsAuthorization(string $authId): array
    {
        try {
            $name = "projects/{$this->projectId}/locations/{$this->location}/dnsAuthorizations/{$authId}";
            
            /** @var \Google\Service\CertificateManager\Operation */
            $operation = $this->certificateManager->projects_locations_dnsAuthorizations->delete($name);

            // オペレーション完了を待機
            $this->waitForOperation($operation->getName());

            Log::info('Google DNS authorization deleted', [
                'authorization_id' => $authId
            ]);

            return [
                'success' => true,
                'operation_name' => $operation->getName()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to delete DNS authorization', [
                'authorization_id' => $authId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to delete DNS authorization: ' . $e->getMessage());
        }
    }

    /**
     * Wait for long-running operation to complete
     */
    private function waitForOperation(string $operationName, int $maxWaitSeconds = 300): void
    {
        $startTime = time();
        
        while (time() - $startTime < $maxWaitSeconds) {
            try {
                /** @var \Google\Service\CertificateManager\Operation */
                $operation = $this->certificateManager->projects_locations_operations->get($operationName);
                
                if ($operation->getDone()) {
                    if ($operation->getError()) {
                        throw new \Exception('Operation failed: ' . json_encode($operation->getError()));
                    }
                    return;
                }
                
                sleep(5); // 5秒待機
                
            } catch (\Exception $e) {
                Log::warning('Error checking operation status', [
                    'operation' => $operationName,
                    'error' => $e->getMessage()
                ]);
                break;
            }
        }
        
        throw new \Exception("Operation timeout: {$operationName}");
    }

    /**
     * Generate certificate ID from domain
     */
    private function generateCertificateId(string $domain): string
    {
        $cleanDomain = str_replace(['*', '.'], ['wildcard', '-'], $domain);
        return 'cert-' . $cleanDomain . '-' . substr(md5($domain . time()), 0, 8);
    }

    /**
     * Generate authorization ID from domain
     */
    private function generateAuthorizationId(string $domain): string
    {
        $cleanDomain = str_replace(['*', '.'], ['wildcard', '-'], $domain);
        return 'dns-auth-' . $cleanDomain . '-' . substr(md5($domain . time()), 0, 8);
    }

    /**
     * Test connection to Google Certificate Manager
     */
    public function testConnection(): array
    {
        try {
            // プロジェクトの Certificate Manager API が有効かテスト
            $parent = "projects/{$this->projectId}/locations/{$this->location}";
            
            /** @var \Google\Service\CertificateManager\ListCertificatesResponse */
            $response = $this->certificateManager->projects_locations_certificates->listProjectsLocationsCertificates(
                $parent,
                ['pageSize' => 1]
            );

            return [
                'success' => true,
                'message' => 'Google Certificate Manager connection successful',
                'project_id' => $this->projectId,
                'location' => $this->location,
                'certificates_count' => count($response->getCertificates())
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Google Certificate Manager connection failed',
                'error' => $e->getMessage(),
                'project_id' => $this->projectId
            ];
        }
    }

    /**
     * Get certificate state and validation status
     */
    public function getCertificateStatus(string $certificateId): array
    {
        try {
            $cert = $this->getCertificate($certificateId);
            
            $status = [
                'certificate_id' => $certificateId,
                'state' => $cert['state'],
                'domains' => $cert['managed']['domains'] ?? [],
                'is_active' => $cert['state'] === 'ACTIVE',
                'provisioning_issue' => $cert['managed']['provisioning_issue'] ?? null,
                'expire_time' => $cert['expire_time'] ?? null,
                'dns_authorizations' => []
            ];

            // DNS認証の状態も確認
            if (!empty($cert['managed']['dns_authorizations'])) {
                foreach ($cert['managed']['dns_authorizations'] as $authName) {
                    $authId = basename($authName);
                    try {
                        $authDetails = $this->getDnsAuthorization($authId);
                        $status['dns_authorizations'][] = [
                            'id' => $authId,
                            'domain' => $authDetails['domain'],
                            'state' => $authDetails['state'],
                            'dns_record' => $authDetails['dns_resource_record'] ?? null
                        ];
                    } catch (\Exception $e) {
                        Log::warning('Failed to get DNS auth details', [
                            'auth_id' => $authId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            return $status;

        } catch (\Exception $e) {
            Log::error('Failed to get certificate status', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to get certificate status: ' . $e->getMessage());
        }
    }
}