<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{GoGetSSLService, GoogleCertificateManagerService, CertificateProviderFactory};
use App\Notifications\SSLSystemAlertNotification;
use Illuminate\Support\Facades\{Log, Notification};
use Illuminate\Support\Facades\Cache;

class CompareSSLProvidersCommand extends Command
{
    protected $signature = 'ssl:compare-providers 
                            {--domain=example.com : Domain to test certificate issuance}
                            {--include-costs : Include cost comparison}
                            {--export= : Export results to file (json, csv)}
                            {--notify : Send Slack notifications}';

    protected $description = 'Compare SSL certificate providers (GoGetSSL vs Google Certificate Manager)';

    public function __construct(
        private readonly CertificateProviderFactory $providerFactory
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $domain = $this->option('domain');
        $includeCosts = $this->option('include-costs');
        $export = $this->option('export');
        $verbose = $this->option('verbose');
        $notify = $this->option('notify');

        $this->info('🔍 Comparing SSL Certificate Providers');
        $this->newLine();

        // 構造化ログ
        Log::info('SSL Provider Comparison started', [
            'command' => 'ssl:compare-providers',
            'options' => $this->options(),
            'domain' => $domain
        ]);

        try {
            $results = [];

            // Test GoGetSSL
            $this->info('📋 Testing GoGetSSL...');
            $goGetSSLResults = $this->testGoGetSSL($domain, $verbose);
            $results['gogetssl'] = $goGetSSLResults;

            $this->newLine();

            // Test Google Certificate Manager
            $this->info('📋 Testing Google Certificate Manager...');
            $googleResults = $this->testGoogleCertificateManager($domain, $verbose);
            $results['google_certificate_manager'] = $googleResults;

            $this->newLine();

            // Display comparison
            $this->displayComparison($results, $includeCosts, $verbose);

            // Export if requested
            if ($export) {
                $this->exportResults($results, $export);
            }

            // 異常検知とSlack通知
            $this->detectAnomaliesAndNotify($results, $notify);

            // 構造化ログ
            Log::info('SSL Provider Comparison completed successfully', array_merge($results, [
                'status' => 'success',
                'domain_tested' => $domain
            ]));

            $this->info('✅ Provider comparison completed successfully');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Comparison failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('Trace: ' . $e->getTraceAsString());
            }

            // 重大エラーのSlack通知
            if ($notify) {
                $this->sendSlackAlert(
                    'SSL Provider Comparison Failed',
                    'SSL provider comparison command failed with exception',
                    [
                        'Error' => $e->getMessage(),
                        'File' => $e->getFile() . ':' . $e->getLine(),
                        'Command' => $this->signature,
                        'Domain' => $domain
                    ],
                    'critical'
                );
            }

            Log::error('SSL Provider Comparison failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'domain' => $domain
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Test GoGetSSL provider
     */
    private function testGoGetSSL(string $domain, bool $verbose): array
    {
        $includeCosts = $this->option('include-costs');
        $results = [
            'provider' => 'GoGetSSL',
            'connection_status' => 'unknown',
            'products_available' => 0,
            'domain_emails' => [],
            'features' => [],
            'limitations' => [],
            'pricing' => [],
            'response_time' => 0,
            'error' => null
        ];

        try {
            $startTime = microtime(true);
            $goGetSSL = $this->providerFactory->createGoGetSSLProvider();

            // Test connection
            $connectionTest = $goGetSSL->testConnection();
            $results['connection_status'] = $connectionTest['success'] ? 'connected' : 'failed';
            
            if (!$connectionTest['success']) {
                $results['error'] = $connectionTest['error'] ?? 'Connection failed';
                return $results;
            }

            if ($verbose) {
                $this->line('  ✅ Connection successful');
            }

            // Get products
            $products = $goGetSSL->getProducts();
            $results['products_available'] = count($products);
            
            if ($verbose) {
                $this->line("  📦 Products available: " . count($products));
            }

            // Test domain email retrieval
            try {
                $domainEmails = $goGetSSL->getApprovalEmails($domain);
                $results['domain_emails'] = $domainEmails;
                
                if ($verbose) {
                    $this->line("  📧 Approval emails for {$domain}: " . count($domainEmails));
                }
            } catch (\Exception $e) {
                if ($verbose) {
                    $this->warn("  ⚠️  Could not retrieve domain emails: " . $e->getMessage());
                }
            }

            // Get account info for features
            try {
                $accountInfo = $goGetSSL->getAccountInfo();
                $balance = $goGetSSL->getBalance();
                
                $results['features'] = [
                    'api_access' => true,
                    'domain_validation' => true,
                    'organization_validation' => true,
                    'extended_validation' => true,
                    'wildcard_certificates' => true,
                    'multi_domain_san' => true,
                    'automatic_renewal' => false,
                    'revocation' => true,
                    'balance_checking' => true,
                    'order_management' => true
                ];

                $results['limitations'] = [
                    'manual_validation_required' => true,
                    'approval_email_dependency' => true,
                    'rate_limits' => 'API dependent',
                    'validation_time' => '5-30 minutes',
                    'certificate_format' => 'Standard formats'
                ];

                if ($includeCosts && isset($balance['balance'])) {
                    $results['pricing'] = [
                        'current_balance' => $balance['balance'],
                        'currency' => $balance['currency'] ?? 'USD',
                        'pricing_model' => 'Per certificate',
                        'dv_certificates' => 'From $5/year',
                        'ov_certificates' => 'From $50/year',
                        'ev_certificates' => 'From $150/year',
                        'wildcard' => 'From $100/year'
                    ];
                }

            } catch (\Exception $e) {
                if ($verbose) {
                    $this->warn("  ⚠️  Could not retrieve account details: " . $e->getMessage());
                }
            }

            $results['response_time'] = round((microtime(true) - $startTime) * 1000, 2);

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            $results['connection_status'] = 'error';
        }

        return $results;
    }

    /**
     * Test Google Certificate Manager
     */
    private function testGoogleCertificateManager(string $domain, bool $verbose): array
    {
        $includeCosts = $this->option('include-costs');
        $results = [
            'provider' => 'Google Certificate Manager',
            'connection_status' => 'unknown',
            'products_available' => 0,
            'domain_emails' => [],
            'features' => [],
            'limitations' => [],
            'pricing' => [],
            'response_time' => 0,
            'error' => null
        ];

        try {
            $startTime = microtime(true);
            $googleCM = $this->providerFactory->createGoogleCertificateManagerProvider();

            // Test connection
            $connectionTest = $googleCM->testConnection();
            $results['connection_status'] = $connectionTest['success'] ? 'connected' : 'failed';
            
            if (!$connectionTest['success']) {
                $results['error'] = $connectionTest['error'] ?? 'Connection failed';
                return $results;
            }

            if ($verbose) {
                $this->line('  ✅ Connection successful');
            }

            // Google Certificate Manager features
            $results['features'] = [
                'api_access' => true,
                'domain_validation' => true,
                'organization_validation' => false, // GCM focuses on DV
                'extended_validation' => false,
                'wildcard_certificates' => true,
                'multi_domain_san' => true,
                'automatic_renewal' => true, // Key advantage
                'revocation' => true,
                'load_balancer_integration' => true,
                'dns_authorization' => true,
                'managed_certificates' => true
            ];

            $results['limitations'] = [
                'google_cloud_only' => true,
                'domain_ownership_required' => true,
                'limited_certificate_types' => 'DV only',
                'validation_time' => '10-60 minutes',
                'certificate_format' => 'Google managed'
            ];

            // Simulate products (Google CM doesn't have traditional "products")
            $results['products_available'] = 1; // Managed SSL certificates

            if ($verbose) {
                $this->line('  📦 Google-managed SSL certificates available');
            }

            // DNS authorization check (simulated)
            $results['domain_emails'] = ['DNS-based validation only'];

            if ($includeCosts) {
                $results['pricing'] = [
                    'pricing_model' => 'Per certificate per month',
                    'managed_certificates' => '$0.75/month per certificate',
                    'certificate_map_entries' => '$0.10/month per entry',
                    'dns_authorizations' => 'Free',
                    'load_balancer_integration' => 'Included',
                    'auto_renewal' => 'Included'
                ];
            }

            $results['response_time'] = round((microtime(true) - $startTime) * 1000, 2);

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            $results['connection_status'] = 'error';
        }

        return $results;
    }

    /**
     * Display comparison results
     */
    private function displayComparison(array $results, bool $includeCosts, bool $verbose): void
    {
        $this->info('📊 SSL Provider Comparison Results');
        $this->newLine();

        // Connection Status
        $this->info('🔗 Connection Status:');
        foreach ($results as $provider => $data) {
            $status = $data['connection_status'];
            $icon = match($status) {
                'connected' => '✅',
                'failed' => '❌',
                'error' => '🔴',
                default => '❓'
            };
            $this->line("  {$icon} {$data['provider']}: {$status}");
            
            if ($data['error']) {
                $this->line("    Error: {$data['error']}");
            }
        }
        $this->newLine();

        // Response Time
        $this->info('⚡ Response Time:');
        foreach ($results as $provider => $data) {
            if ($data['response_time'] > 0) {
                $this->line("  {$data['provider']}: {$data['response_time']}ms");
            }
        }
        $this->newLine();

        // Features Comparison
        $this->info('🎯 Features Comparison:');
        $this->displayFeaturesTable($results);

        // Products/Capabilities
        $this->info('📦 Products/Capabilities:');
        foreach ($results as $provider => $data) {
            $this->line("  {$data['provider']}: {$data['products_available']} products/types available");
        }
        $this->newLine();

        // Pricing (if requested)
        if ($includeCosts) {
            $this->info('💰 Pricing Comparison:');
            foreach ($results as $provider => $data) {
                if (!empty($data['pricing'])) {
                    $this->line("  {$data['provider']}:");
                    foreach ($data['pricing'] as $key => $value) {
                        $this->line("    - " . str_replace('_', ' ', ucfirst($key)) . ": {$value}");
                    }
                    $this->newLine();
                }
            }
        }

        // Recommendations
        $this->displayRecommendations($results);
    }

    /**
     * Display features comparison table
     */
    private function displayFeaturesTable(array $results): void
    {
        $allFeatures = [];
        foreach ($results as $data) {
            $allFeatures = array_merge($allFeatures, array_keys($data['features'] ?? []));
        }
        $allFeatures = array_unique($allFeatures);

        $tableData = [];
        foreach ($allFeatures as $feature) {
            $row = [str_replace('_', ' ', ucfirst($feature))];
            foreach ($results as $data) {
                $hasFeature = $data['features'][$feature] ?? false;
                $row[] = $hasFeature ? '✅' : '❌';
            }
            $tableData[] = $row;
        }

        $headers = ['Feature'];
        foreach ($results as $data) {
            $headers[] = $data['provider'];
        }

        $this->table($headers, $tableData);
        $this->newLine();
    }

    /**
     * Display recommendations
     */
    private function displayRecommendations(array $results): void
    {
        $this->info('💡 Recommendations:');
        $this->newLine();

        $goGetSSL = $results['gogetssl'] ?? null;
        $google = $results['google_certificate_manager'] ?? null;

        if ($goGetSSL && $goGetSSL['connection_status'] === 'connected') {
            $this->line('📋 GoGetSSL is suitable for:');
            $this->line('  • Traditional SSL certificate needs');
            $this->line('  • OV and EV certificates');
            $this->line('  • Multi-provider setups');
            $this->line('  • Cost-effective solutions');
            $this->newLine();
        }

        if ($google && $google['connection_status'] === 'connected') {
            $this->line('☁️  Google Certificate Manager is suitable for:');
            $this->line('  • Google Cloud Platform deployments');
            $this->line('  • Automatic certificate management');
            $this->line('  • Load balancer integration');
            $this->line('  • Simplified DV certificate needs');
            $this->newLine();
        }

        $this->line('🎯 Best Practice:');
        $this->line('  • Use Google CM for GCP-hosted applications');
        $this->line('  • Use GoGetSSL for diverse hosting environments');
        $this->line('  • Consider hybrid approach for different use cases');
    }

    /**
     * Export results to file
     */
    private function exportResults(array $results, string $format): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "ssl_provider_comparison_{$timestamp}.{$format}";
        $filepath = storage_path("app/exports/{$filename}");

        $directory = dirname($filepath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $exportData = [
            'comparison_date' => now()->toISOString(),
            'domain_tested' => $this->option('domain'),
            'providers' => $results
        ];

        switch (strtolower($format)) {
            case 'json':
                file_put_contents($filepath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                break;
            case 'csv':
                $this->exportToCsv($results, $filepath);
                break;
            default:
                $this->error("Unsupported export format: {$format}");
                return;
        }

        $this->info("Results exported to: {$filepath}");
    }

    /**
     * Export results to CSV
     */
    private function exportToCsv(array $results, string $filepath): void
    {
        $fp = fopen($filepath, 'w');
        
        if ($fp === false) {
            throw new \RuntimeException("Cannot open file for writing: {$filepath}");
        }

        // Headers
        fputcsv($fp, [
            'Provider',
            'Connection Status',
            'Products Available',
            'Response Time (ms)',
            'Domain Validation',
            'Organization Validation',
            'Extended Validation',
            'Wildcard Support',
            'Auto Renewal',
            'Error'
        ]);

        // Data
        foreach ($results as $data) {
            fputcsv($fp, [
                $data['provider'],
                $data['connection_status'],
                $data['products_available'],
                $data['response_time'],
                ($data['features']['domain_validation'] ?? false) ? 'Yes' : 'No',
                ($data['features']['organization_validation'] ?? false) ? 'Yes' : 'No',
                ($data['features']['extended_validation'] ?? false) ? 'Yes' : 'No',
                ($data['features']['wildcard_certificates'] ?? false) ? 'Yes' : 'No',
                ($data['features']['automatic_renewal'] ?? false) ? 'Yes' : 'No',
                $data['error'] ?? ''
            ]);
        }

        fclose($fp);
    }

    /**
     * 異常検知とSlack通知
     */
    private function detectAnomaliesAndNotify(array $results, bool $notify): void
    {
        if (!$notify && !config('ssl-enhanced.monitoring.alert_on_failure', true)) {
            return;
        }

        $failedProviders = array_filter($results, function ($result) {
            return in_array($result['connection_status'], ['failed', 'error']);
        });

        // プロバイダー接続失敗の検知
        if (!empty($failedProviders)) {
            $this->sendSlackAlert(
                'SSL Provider Connection Issues',
                count($failedProviders) . ' SSL providers are experiencing connection issues',
                [
                    'Failed Providers' => implode(', ', array_column($failedProviders, 'provider')),
                    'Total Providers Tested' => count($results),
                    'Failure Rate' => round((count($failedProviders) / count($results)) * 100, 1) . '%',
                    'Domain Tested' => $this->option('domain')
                ],
                'error'
            );
        }

        // 成功時の定期報告（週1回）
        if (empty($failedProviders) && $this->shouldSendWeeklyReport()) {
            $this->sendSlackAlert(
                'SSL Provider Comparison - All Systems Healthy',
                'SSL provider comparison completed successfully',
                [
                    'Providers Tested' => count($results),
                    'All Connections' => 'Successful',
                    'Domain Tested' => $this->option('domain'),
                    'Features Compared' => 'OK'
                ],
                'success'
            );
        }
    }

    /**
     * Slack通知送信
     */
    private function sendSlackAlert(string $title, string $message, array $details = [], string $severity = 'warning'): void
    {
        try {
            if (!config('services.slack.notifications.webhook_url')) {
                Log::warning('Slack webhook URL not configured, skipping alert', [
                    'title' => $title,
                    'severity' => $severity
                ]);
                return;
            }

            Notification::route('slack', config('services.slack.notifications.webhook_url'))
                ->notify(new SSLSystemAlertNotification($title, $message, $details, $severity));

            Log::info('SSL comparison Slack alert sent successfully', [
                'title' => $title,
                'severity' => $severity,
                'details_count' => count($details)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send SSL comparison Slack alert', [
                'error' => $e->getMessage(),
                'title' => $title,
                'severity' => $severity
            ]);
        }
    }

    /**
     * 週次レポートが必要かチェック
     */
    private function shouldSendWeeklyReport(): bool
    {
        // 週1回の定期報告（日曜日）
        if (now()->dayOfWeek !== 0) {
            return false;
        }

        // 今週既に送信済みかチェック
        $lastReportWeek = Cache::get('ssl_comparison_weekly_report_last_sent');
        $currentWeek = now()->format('Y-W');
        
        if ($lastReportWeek === $currentWeek) {
            return false;
        }

        // 週次レポート送信をマーク
        Cache::put('ssl_comparison_weekly_report_last_sent', $currentWeek, now()->addWeeks(2));
        
        return true;
    }
}