<?php

namespace App\Console\Commands;

use App\Services\GoogleCertificateManagerService;
use Illuminate\Console\Command;

class TestGoogleCertificateManagerCommand extends Command
{
    protected $signature = 'google-cert:test 
                            {--create-test-cert : Create a test certificate}
                            {--domain=test.example.com : Domain for test certificate}
                            {--list-certs : List existing certificates}
                            {--cleanup : Delete test certificates}';
    
    protected $description = 'Test Google Certificate Manager API connection and operations';

    public function handle(): int
    {
        $this->info('Testing Google Certificate Manager connection...');

        try {
            /** @var GoogleCertificateManagerService */
            $service = app(GoogleCertificateManagerService::class);

            // 接続テスト
            $connectionResult = $service->testConnection();
            
            if (!$connectionResult['success']) {
                $this->error('❌ Connection test failed: ' . ($connectionResult['error'] ?? 'Unknown error'));
                return self::FAILURE;
            }

            $this->info('✅ Connection successful');
            $this->line('📋 Project ID: ' . ($connectionResult['project_id'] ?? 'N/A'));
            $this->line('📍 Location: ' . ($connectionResult['location'] ?? 'N/A'));
            $this->line('📜 Existing certificates: ' . ($connectionResult['certificates_count'] ?? 0));

            // 証明書一覧の表示
            if ($this->option('list-certs')) {
                $this->listCertificates($service);
            }

            // テスト証明書の作成
            if ($this->option('create-test-cert')) {
                $domain = $this->option('domain');
                $this->createTestCertificate($service, $domain);
            }

            // クリーンアップ
            if ($this->option('cleanup')) {
                $this->cleanupTestCertificates($service);
            }

            $this->info('🎉 Google Certificate Manager test completed');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Google Certificate Manager test failed: ' . $e->getMessage());
            
            $this->line('');
            $this->line('🔧 Troubleshooting:');
            $this->line('   1. Check GOOGLE_CLOUD_PROJECT_ID in .env');
            $this->line('   2. Verify GOOGLE_APPLICATION_CREDENTIALS path or GOOGLE_SERVICE_ACCOUNT_KEY_JSON');
            $this->line('   3. Ensure Certificate Manager API is enabled in Google Cloud');
            $this->line('   4. Check service account permissions');
            
            return self::FAILURE;
        }
    }

    /**
     * List existing certificates
     */
    private function listCertificates(GoogleCertificateManagerService $service): void
    {
        try {
            $this->line('');
            $this->info('📜 Listing existing certificates...');

            $certificates = $service->listCertificates(['page_size' => 10]);
            
            if (empty($certificates['certificates'])) {
                $this->line('   No certificates found');
                return;
            }

            $this->table(
                ['Name', 'State', 'Created', 'Expires', 'Domains'],
                array_map(function ($cert) {
                    return [
                        basename($cert['name']),
                        $cert['state'] ?? 'Unknown',
                        isset($cert['create_time']) ? 
                            \Carbon\Carbon::parse($cert['create_time'])->format('Y-m-d H:i') : 'N/A',
                        isset($cert['expire_time']) ? 
                            \Carbon\Carbon::parse($cert['expire_time'])->format('Y-m-d H:i') : 'N/A',
                        is_array($cert['subject_alternative_names']) ? 
                            implode(', ', array_slice($cert['subject_alternative_names'], 0, 3)) : 'N/A'
                    ];
                }, $certificates['certificates'])
            );

            if (count($certificates['certificates']) >= 10) {
                $this->warn('   Showing first 10 certificates only. Use --page-size for more.');
            }

        } catch (\Exception $e) {
            $this->warn('   Failed to list certificates: ' . $e->getMessage());
        }
    }

    /**
     * Create test certificate
     */
    private function createTestCertificate(GoogleCertificateManagerService $service, string $domain): void
    {
        try {
            $this->line('');
            $this->info("📋 Creating test certificate for domain: {$domain}");
            
            if (!$this->isValidTestDomain($domain)) {
                $this->warn("⚠️  Domain '{$domain}' may not be suitable for testing");
                if (!$this->confirm('Continue anyway?', false)) {
                    return;
                }
            }

            // DNS認証の作成
            $this->line('   Creating DNS authorization...');
            $dnsAuth = $service->createDnsAuthorization($domain);
            
            $this->info('✅ DNS authorization created');
            $this->line('   Authorization ID: ' . $dnsAuth['authorization_id']);
            $this->line('   State: ' . ($dnsAuth['state'] ?? 'Unknown'));
            
            if (isset($dnsAuth['dns_record'])) {
                $this->line('   DNS Record Required:');
                $this->line('     Name: ' . ($dnsAuth['dns_record']['name'] ?? 'N/A'));
                $this->line('     Type: ' . ($dnsAuth['dns_record']['type'] ?? 'N/A'));
                $this->line('     Value: ' . ($dnsAuth['dns_record']['data'] ?? 'N/A'));
            }

            // マネージド証明書の作成
            $this->line('   Creating managed certificate...');
            $certificate = $service->createManagedCertificate([$domain], [
                'description' => 'Test certificate created by SSL SaaS Platform'
            ]);

            $this->info('✅ Test certificate creation initiated');
            $this->line('   Certificate ID: ' . $certificate['certificate_id']);
            $this->line('   State: ' . ($certificate['state'] ?? 'Unknown'));
            
            $this->line('');
            $this->warn('⚠️  Important: Add the DNS record above to complete validation');
            $this->warn('⚠️  Certificate provisioning may take 15-60 minutes after DNS validation');
            
            // 状態監視の提案
            $this->line('');
            $this->line('💡 Monitor certificate status with:');
            $this->line("   php artisan google-cert:status {$certificate['certificate_id']}");

        } catch (\Exception $e) {
            $this->error('   Failed to create test certificate: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup test certificates
     */
    private function cleanupTestCertificates(GoogleCertificateManagerService $service): void
    {
        try {
            $this->line('');
            $this->info('🧹 Cleaning up test certificates...');

            $certificates = $service->listCertificates();
            $testCertificates = array_filter($certificates['certificates'], function ($cert) {
                $description = $cert['description'] ?? '';
                return str_contains(strtolower($description), 'test') || 
                       str_contains(strtolower($description), 'ssl saas platform');
            });

            if (empty($testCertificates)) {
                $this->line('   No test certificates found to cleanup');
                return;
            }

            $this->line('   Found ' . count($testCertificates) . ' test certificate(s)');

            if (!$this->confirm('Delete these test certificates?', false)) {
                return;
            }

            $deleted = 0;
            foreach ($testCertificates as $cert) {
                try {
                    $certId = basename($cert['name']);
                    $service->deleteCertificate($certId);
                    $this->line("   ✅ Deleted certificate: {$certId}");
                    $deleted++;
                } catch (\Exception $e) {
                    $this->warn("   ❌ Failed to delete {$certId}: " . $e->getMessage());
                }
            }

            $this->info("   Cleanup completed. Deleted {$deleted} certificate(s)");

        } catch (\Exception $e) {
            $this->warn('   Cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if domain is valid for testing
     */
    private function isValidTestDomain(string $domain): bool
    {
        // 本番環境で重要なドメインを避ける
        $productionIndicators = [
            'www.',
            'api.',
            'app.',
            'mail.',
            'admin.',
            '.com',
            '.org',
            '.net',
            '.co.uk'
        ];

        $testIndicators = [
            'test.',
            'staging.',
            'dev.',
            '.test',
            '.local',
            'example.com',
            'example.org'
        ];

        // テスト用ドメインの場合はOK
        foreach ($testIndicators as $indicator) {
            if (str_contains($domain, $indicator)) {
                return true;
            }
        }

        // 本番用ドメインの場合は警告
        foreach ($productionIndicators as $indicator) {
            if (str_contains($domain, $indicator)) {
                return false;
            }
        }

        return true;
    }
}