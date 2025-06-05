<?php

namespace App\Console\Commands;

use App\Services\GoogleCertificateManagerService;
use App\Notifications\SSLSystemAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Log, Cache, Notification};

class TestGoogleCertificateManagerCommand extends Command
{
    protected $signature = 'google-cert:test 
                            {--create-test-cert : Create a test certificate}
                            {--domain=test.example.com : Domain for test certificate}
                            {--list-certs : List existing certificates}
                            {--cleanup : Delete test certificates}
                            {--notify-slack : Send results to Slack}';
    
    protected $description = 'Test Google Certificate Manager API connection and operations';

    public function handle(): int
    {
        $this->info('Testing Google Certificate Manager connection...');

        // 構造化ログ
        Log::info('Google Certificate Manager test started', [
            'command' => 'google-cert:test',
            'options' => $this->options(),
            'domain' => $this->option('domain')
        ]);

        try {
            /** @var GoogleCertificateManagerService */
            $service = app(GoogleCertificateManagerService::class);

            // 接続テスト
            $connectionResult = $service->testConnection();
            if (!$connectionResult['success']) {
                $this->error('❌ Connection test failed: ' . ($connectionResult['error'] ?? 'Unknown error'));
                
                // 接続失敗のSlack通知
                $this->sendSlackAlert(
                    'Google Certificate Manager Connection Failed',
                    'Failed to connect to Google Certificate Manager API',
                    [
                        'Error' => $connectionResult['error'] ?? 'Unknown error',
                        'Project ID' => config('services.google.project_id'),
                        'Location' => config('services.google.certificate_manager.location')
                    ],
                    'error'
                );

                Log::error('Google Certificate Manager connection failed', [
                    'error' => $connectionResult['error'] ?? 'Unknown error',
                    'config_check' => $this->validateConfiguration()
                ]);

                return self::FAILURE;
            }

            $this->info('✅ Connection successful');
            $this->line('📋 Project ID: ' . ($connectionResult['project_id'] ?? 'N/A'));
            $this->line('📍 Location: ' . ($connectionResult['location'] ?? 'N/A'));
            $this->line('📜 Existing certificates: ' . ($connectionResult['certificates_count'] ?? 0));

            $testResults = [
                'connection_status' => 'success',
                'project_id' => $connectionResult['project_id'] ?? 'N/A',
                'location' => $connectionResult['location'] ?? 'N/A',
                'certificates_count' => $connectionResult['certificates_count'] ?? 0,
                'tests_performed' => []
            ];

            // 証明書一覧の表示
            if ($this->option('list-certs')) {
                $listResult = $this->listCertificates($service);
                $testResults['tests_performed'][] = 'list_certificates';
                $testResults['list_certificates_result'] = $listResult;
            }

            // テスト証明書の作成
            if ($this->option('create-test-cert')) {
                $domain = $this->option('domain');
                $createResult = $this->createTestCertificate($service, $domain);
                $testResults['tests_performed'][] = 'create_test_certificate';
                $testResults['create_certificate_result'] = $createResult;
            }

            // クリーンアップ
            if ($this->option('cleanup')) {
                $cleanupResult = $this->cleanupTestCertificates($service);
                $testResults['tests_performed'][] = 'cleanup_test_certificates';
                $testResults['cleanup_result'] = $cleanupResult;
            }

            // 成功時の通知とログ
            $this->detectAnomaliesAndNotify($testResults);

            // 構造化ログ
            Log::info('Google Certificate Manager test completed successfully', array_merge($testResults, [
                'status' => 'success',
                'performance_metrics' => $this->getPerformanceMetrics($testResults)
            ]));

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

            // 重大エラーのSlack通知
            $this->sendSlackAlert(
                'Google Certificate Manager Test Failed',
                'Google Certificate Manager test command failed with exception',
                [
                    'Error' => $e->getMessage(),
                    'File' => $e->getFile() . ':' . $e->getLine(),
                    'Command' => $this->signature,
                    'Configuration Issues' => $this->validateConfiguration()
                ],
                'critical'
            );

            Log::error('Google Certificate Manager test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'configuration' => $this->validateConfiguration()
            ]);
            
            return self::FAILURE;
        }
    }

    /**
     * List existing certificates
     */
    private function listCertificates(GoogleCertificateManagerService $service): array
    {
        try {
            $this->line('');
            $this->info('📜 Listing existing certificates...');

            $certificates = $service->listCertificates(['page_size' => 10]);
            
            if (empty($certificates['certificates'])) {
                $this->line('   No certificates found');
                return ['status' => 'success', 'count' => 0];
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

            return [
                'status' => 'success', 
                'count' => count($certificates['certificates']),
                'certificates' => $certificates['certificates']
            ];

        } catch (\Exception $e) {
            $this->warn('   Failed to list certificates: ' . $e->getMessage());
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Create test certificate
     */
    private function createTestCertificate(GoogleCertificateManagerService $service, string $domain): array
    {
        try {
            $this->line('');
            $this->info("📋 Creating test certificate for domain: {$domain}");
            
            if (!$this->isValidTestDomain($domain)) {
                $this->warn("⚠️  Domain '{$domain}' may not be suitable for testing");
                if (!$this->confirm('Continue anyway?', false)) {
                    return ['status' => 'cancelled', 'reason' => 'User cancelled unsafe domain test'];
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

            return [
                'status' => 'success',
                'certificate_id' => $certificate['certificate_id'],
                'domain' => $domain,
                'dns_authorization' => $dnsAuth,
                'certificate_state' => $certificate['state'] ?? 'Unknown'
            ];

        } catch (\Exception $e) {
            $this->error('   Failed to create test certificate: ' . $e->getMessage());
            return ['status' => 'error', 'error' => $e->getMessage(), 'domain' => $domain];
        }
    }

    /**
     * Cleanup test certificates
     */
    private function cleanupTestCertificates(GoogleCertificateManagerService $service): array
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
                return ['status' => 'success', 'deleted_count' => 0];
            }

            $this->line('   Found ' . count($testCertificates) . ' test certificate(s)');

            if (!$this->confirm('Delete these test certificates?', false)) {
                return ['status' => 'cancelled', 'reason' => 'User cancelled deletion'];
            }

            $deleted = 0;
            $errors = [];
            foreach ($testCertificates as $cert) {
                try {
                    $certId = basename($cert['name']);
                    $service->deleteCertificate($certId);
                    $this->line("   ✅ Deleted certificate: {$certId}");
                    $deleted++;
                } catch (\Exception $e) {
                    $error = "Failed to delete {$certId}: " . $e->getMessage();
                    $this->warn("   ❌ {$error}");
                    $errors[] = $error;
                }
            }

            $this->info("   Cleanup completed. Deleted {$deleted} certificate(s)");

            return [
                'status' => 'success',
                'deleted_count' => $deleted,
                'total_found' => count($testCertificates),
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->warn('   Cleanup failed: ' . $e->getMessage());
            return ['status' => 'error', 'error' => $e->getMessage()];
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

    /**
     * 設定の検証
     */
    private function validateConfiguration(): array
    {
        return [
            'project_id_set' => !empty(config('services.google.project_id')),
            'credentials_set' => !empty(config('services.google.credentials')) || !empty(config('services.google.key_data')),
            'api_enabled' => class_exists('Google\Cloud\CertificateManager\V1\CertificateManagerClient'),
            'location_configured' => !empty(config('services.google.certificate_manager.location'))
        ];
    }

    /**
     * パフォーマンスメトリクスの取得
     */
    private function getPerformanceMetrics(array $testResults): array
    {
        return [
            'tests_count' => count($testResults['tests_performed']),
            'certificates_count' => $testResults['certificates_count'],
            'has_connection' => $testResults['connection_status'] === 'success',
            'project_configured' => !empty($testResults['project_id']) && $testResults['project_id'] !== 'N/A'
        ];
    }

    /**
     * 異常検知とSlack通知
     */
    private function detectAnomaliesAndNotify(array $testResults): void
    {
        if (!$this->option('notify-slack') && !config('ssl-enhanced.monitoring.alert_on_failure', true)) {
            return;
        }

        // 成功時の定期報告
        if ($this->shouldSendTestReport($testResults)) {
            $this->sendSlackAlert(
                'Google Certificate Manager Test Successful',
                'Google Certificate Manager connection and functionality verified',
                [
                    'Project ID' => $testResults['project_id'],
                    'Location' => $testResults['location'],
                    'Certificates Count' => $testResults['certificates_count'],
                    'Tests Performed' => implode(', ', $testResults['tests_performed']),
                    'Status' => 'All systems operational'
                ],
                'success'
            );
        }

        // 証明書数の異常検知
        if (isset($testResults['certificates_count']) && $testResults['certificates_count'] > 50) {
            $this->sendSlackAlert(
                'High Certificate Count Detected',
                'Unusually high number of certificates found in Google Certificate Manager',
                [
                    'Certificates Count' => $testResults['certificates_count'],
                    'Threshold' => 50,
                    'Action Required' => 'Review and cleanup unused certificates'
                ],
                'warning'
            );
        }
    }

    /**
     * Slack通知送信
     */
    private function sendSlackAlert(string $title, string $message, array $details = [], string $severity = 'info'): void
    {
        try {
            if (!config('services.slack.notifications.webhook_url')) {
                Log::debug('Slack webhook URL not configured, skipping alert', [
                    'title' => $title,
                    'severity' => $severity
                ]);
                return;
            }

            Notification::route('slack', config('services.slack.notifications.webhook_url'))
                ->notify(new SSLSystemAlertNotification($title, $message, $details, $severity));

            Log::info('Google CM test Slack alert sent', [
                'title' => $title,
                'severity' => $severity,
                'details_count' => count($details)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Google CM test Slack alert', [
                'error' => $e->getMessage(),
                'title' => $title,
                'severity' => $severity
            ]);
        }
    }

    /**
     * テスト結果の定期報告が必要かチェック
     */
    private function shouldSendTestReport(array $testResults): bool
    {
        // 明示的にSlack通知が要求された場合
        if ($this->option('notify-slack')) {
            return true;
        }

        // 毎日の定期テスト時のみ（週1回程度）
        $lastReportDate = Cache::get('google_cm_test_last_reported');
        if ($lastReportDate && $lastReportDate > now()->subDays(7)) {
            return false;
        }

        // 成功した場合のみ定期報告
        if ($testResults['connection_status'] === 'success') {
            Cache::put('google_cm_test_last_reported', now(), now()->addDays(14));
            return true;
        }

        return false;
    }
}