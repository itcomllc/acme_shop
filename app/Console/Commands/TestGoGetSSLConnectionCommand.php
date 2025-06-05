<?php

namespace App\Console\Commands;

use App\Services\GoGetSSLService;
use App\Notifications\SSLSystemAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Log, Notification};

class TestGoGetSSLConnectionCommand extends Command
{
    protected $signature = 'gogetssl:test-connection 
                            {--clear-cache : Clear auth cache before testing} 
                            {--domain= : Test domain email retrieval}
                            {--silent : Minimal output mode}
                            {--notify : Send Slack notifications on failures}';
    
    protected $description = 'Test GoGetSSL API connection and functionality';

    public function __construct(
        private readonly GoGetSSLService $goGetSSLService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->option('silent')) {
            $this->info('🔍 GoGetSSL API接続をテストしています...');
        }

        // 構造化ログ
        Log::info('GoGetSSL connection test started', [
            'command' => 'gogetssl:test-connection',
            'options' => $this->options()
        ]);

        try {
            // キャッシュクリアオプション
            if ($this->option('clear-cache')) {
                $this->goGetSSLService->clearAuthCache();
                if (!$this->option('silent')) {
                    $this->line('🧹 認証キャッシュをクリアしました');
                }
            }
            
            // 基本接続テスト
            $connectionResult = $this->testBasicConnection();
            if (!$connectionResult) {
                return self::FAILURE;
            }
            
            // アカウント情報取得テスト
            $accountResult = $this->testAccountInfo();
            
            // 残高取得テスト
            $balanceResult = $this->testBalance();
            
            // 商品一覧取得テスト
            $productsResult = $this->testProducts();
            
            // ドメインメール取得テスト（オプション）
            $domainEmailResult = null;
            if ($domain = $this->option('domain')) {
                $domainEmailResult = $this->testDomainEmails($domain);
            }
            
            // 使用方法のヒント表示
            if (!$this->option('silent')) {
                $this->showUsageHints();
            }
            
            // テスト結果の総括
            $testResults = [
                'connection' => $connectionResult,
                'account_info' => $accountResult,
                'balance' => $balanceResult,
                'products' => $productsResult,
                'domain_emails' => $domainEmailResult
            ];
            
            // 成功通知
            if ($this->option('notify') && config('services.slack.notifications.webhook_url')) {
                $this->sendSuccessNotification($testResults);
            }
            
            if (!$this->option('silent')) {
                $this->info('🎉 GoGetSSL API接続テスト完了');
            }
            
            Log::info('GoGetSSL connection test completed successfully', [
                'test_time' => now()->toISOString(),
                'results' => $testResults
            ]);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ GoGetSSL API接続エラー: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('詳細エラー: ' . $e->getTraceAsString());
            }
            
            if (!$this->option('silent')) {
                $this->showTroubleshooting();
            }
            
            // 失敗通知
            if ($this->option('notify')) {
                $this->sendFailureNotification($e);
            }
            
            Log::error('GoGetSSL connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => $this->options()
            ]);
            
            return self::FAILURE;
        }
    }

    /**
     * 基本接続テスト
     */
    private function testBasicConnection(): bool
    {
        try {
            $connectionResult = $this->goGetSSLService->testConnection();
            
            if (!$connectionResult['success']) {
                $this->error('❌ 接続テストに失敗しました: ' . ($connectionResult['error'] ?? 'Unknown error'));
                
                if ($this->option('notify')) {
                    $this->sendSlackAlert(
                        'GoGetSSL Connection Failed',
                        'Basic connection test to GoGetSSL API failed',
                        [
                            'Error' => $connectionResult['error'] ?? 'Unknown error',
                            'Service' => 'GoGetSSL API'
                        ],
                        'critical'
                    );
                }
                
                return false;
            }
            
            if (!$this->option('silent')) {
                $this->info('✅ 認証成功 - Auth key取得完了');
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->error('❌ 接続テスト中にエラーが発生: ' . $e->getMessage());
            
            if ($this->option('notify')) {
                $this->sendSlackAlert(
                    'GoGetSSL Connection Exception',
                    'Exception occurred during GoGetSSL connection test',
                    [
                        'Exception' => $e->getMessage(),
                        'File' => $e->getFile() . ':' . $e->getLine()
                    ],
                    'critical'
                );
            }
            
            throw $e;
        }
    }

    /**
     * アカウント情報取得テスト
     */
    private function testAccountInfo(): array
    {
        try {
            $accountInfo = $this->goGetSSLService->getAccountInfo();
            
            if (!$this->option('silent')) {
                $this->line('📋 アカウント情報:');
                $this->line('   名前: ' . ($accountInfo['first_name'] ?? 'N/A') . ' ' . ($accountInfo['last_name'] ?? 'N/A'));
                $this->line('   会社名: ' . ($accountInfo['company_name'] ?? 'N/A'));
                $this->line('   メール: ' . ($accountInfo['email'] ?? 'N/A'));
                $this->line('   国: ' . ($accountInfo['country'] ?? 'N/A'));
                $this->line('   通貨: ' . ($accountInfo['currency'] ?? 'N/A'));
                
                if (isset($accountInfo['reseller_plan'])) {
                    $this->line('   リセラープラン: ' . ($accountInfo['reseller_plan'] ?? 'なし'));
                }
            }
            
            return [
                'success' => true,
                'data' => $accountInfo
            ];
            
        } catch (\Exception $e) {
            $this->warn('⚠️  アカウント情報取得に失敗: ' . $e->getMessage());
            
            if ($this->option('notify')) {
                $this->sendSlackAlert(
                    'GoGetSSL Account Info Failed',
                    'Failed to retrieve account information from GoGetSSL',
                    [
                        'Error' => $e->getMessage(),
                        'Function' => 'getAccountInfo'
                    ],
                    'warning'
                );
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 残高取得テスト
     */
    private function testBalance(): array
    {
        try {
            $balance = $this->goGetSSLService->getBalance();
            
            if (!$this->option('silent')) {
                $this->line('💰 残高: ' . ($balance['balance'] ?? 'N/A'));
                $this->line('   通貨: ' . ($balance['currency'] ?? 'N/A'));
            }
            
            // 残高が少ない場合の警告
            $balanceAmount = floatval($balance['balance'] ?? 0);
            if ($balanceAmount > 0 && $balanceAmount < 50) { // 閾値は設定可能
                $this->warn("⚠️  残高が少なくなっています: {$balanceAmount} {$balance['currency']}");
                
                if ($this->option('notify')) {
                    $this->sendSlackAlert(
                        'GoGetSSL Low Balance Warning',
                        'GoGetSSL account balance is running low',
                        [
                            'Balance' => $balanceAmount . ' ' . ($balance['currency'] ?? ''),
                            'Warning Threshold' => '50.00',
                            'Action Required' => 'Consider adding funds to avoid service interruption'
                        ],
                        'warning'
                    );
                }
            }
            
            return [
                'success' => true,
                'data' => $balance
            ];
            
        } catch (\Exception $e) {
            $this->warn('⚠️  残高取得に失敗: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 商品一覧取得テスト
     */
    private function testProducts(): array
    {
        try {
            $products = $this->goGetSSLService->getProducts();
            
            if (!$this->option('silent')) {
                $this->line('📦 利用可能商品数: ' . count($products));
                
                if (count($products) > 0) {
                    $this->line('   主要商品:');
                    foreach (array_slice($products, 0, 3) as $product) {
                        $productName = $product['product'] ?? $product['name'] ?? 'Unknown';
                        $productId = $product['product_id'] ?? $product['id'] ?? 'N/A';
                        $brand = $product['brand'] ?? 'N/A';
                        
                        $this->line("   - {$productName} (ID: {$productId}, ブランド: {$brand})");
                    }
                }
            }
            
            // 商品数が異常に少ない場合の警告
            if (count($products) < 5) {
                $this->warn('⚠️  利用可能な商品数が少ないです。APIの問題またはアカウント制限の可能性があります。');
                
                if ($this->option('notify')) {
                    $this->sendSlackAlert(
                        'GoGetSSL Limited Products Available',
                        'Unusually few SSL products available from GoGetSSL',
                        [
                            'Available Products' => count($products),
                            'Expected Minimum' => '5',
                            'Possible Cause' => 'API issue or account restrictions'
                        ],
                        'warning'
                    );
                }
            }
            
            return [
                'success' => true,
                'data' => $products,
                'count' => count($products)
            ];
            
        } catch (\Exception $e) {
            $this->warn('⚠️  商品一覧取得に失敗: ' . $e->getMessage());
            
            if ($this->option('notify')) {
                $this->sendSlackAlert(
                    'GoGetSSL Products Fetch Failed',
                    'Failed to retrieve product list from GoGetSSL',
                    [
                        'Error' => $e->getMessage(),
                        'Function' => 'getProducts'
                    ],
                    'error'
                );
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ドメインメール取得テスト
     */
    private function testDomainEmails(string $domain): array
    {
        try {
            if (!$this->option('silent')) {
                $this->line('');
                $this->line("📧 ドメイン「{$domain}」の承認メール一覧:");
            }
            
            $domainEmails = $this->goGetSSLService->getDomainEmails($domain);
            
            if (isset($domainEmails['success']) && $domainEmails['success']) {
                if (isset($domainEmails['ComodoApprovalEmails']) && is_array($domainEmails['ComodoApprovalEmails'])) {
                    if (!$this->option('silent')) {
                        $this->line('   🔹 Comodo承認メール (' . count($domainEmails['ComodoApprovalEmails']) . '件):');
                        foreach ($domainEmails['ComodoApprovalEmails'] as $email) {
                            $this->line("     - {$email}");
                        }
                    }
                }
                
                if (isset($domainEmails['GeotrustApprovalEmails']) && is_array($domainEmails['GeotrustApprovalEmails'])) {
                    if (!$this->option('silent')) {
                        $this->line('   🔹 Geotrust承認メール (' . count($domainEmails['GeotrustApprovalEmails']) . '件):');
                        foreach ($domainEmails['GeotrustApprovalEmails'] as $email) {
                            $this->line("     - {$email}");
                        }
                    }
                }

                // 統合版も表示
                $approvalEmails = $this->goGetSSLService->getApprovalEmails($domain);
                if (!empty($approvalEmails)) {
                    if (!$this->option('silent')) {
                        $this->line('   📋 統合承認メール一覧 (' . count($approvalEmails) . '件):');
                        foreach ($approvalEmails as $email) {
                            $this->line("     - {$email}");
                        }
                    }
                } else {
                    if (!$this->option('silent')) {
                        $this->line('   📋 承認メールが見つかりませんでした');
                    }
                    
                    if ($this->option('notify')) {
                        $this->sendSlackAlert(
                            'GoGetSSL No Approval Emails',
                            "No approval emails found for domain: {$domain}",
                            [
                                'Domain' => $domain,
                                'Issue' => 'No approval emails available',
                                'Impact' => 'Certificate validation may be difficult'
                            ],
                            'warning'
                        );
                    }
                }
                
                return [
                    'success' => true,
                    'domain' => $domain,
                    'data' => $domainEmails,
                    'approval_emails' => $approvalEmails
                ];
            } else {
                if (!$this->option('silent')) {
                    $this->warn('   ⚠️  ドメインメール取得に失敗しました');
                    if (isset($domainEmails['error'])) {
                        $this->warn('   エラー: ' . $domainEmails['error']);
                    }
                }
                
                return [
                    'success' => false,
                    'domain' => $domain,
                    'error' => $domainEmails['error'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            if (!$this->option('silent')) {
                $this->warn("   ⚠️  ドメイン「{$domain}」のメール取得に失敗: " . $e->getMessage());
            }
            
            return [
                'success' => false,
                'domain' => $domain,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 使用方法のヒント表示
     */
    private function showUsageHints(): void
    {
        $this->line('');
        $this->line('💡 使用方法のヒント:');
        $this->line('   • キャッシュクリア: --clear-cache');
        $this->line('   • ドメインテスト: --domain=example.com');
        $this->line('   • 詳細出力: --verbose');
        $this->line('   • 静寂モード: --silent');
        $this->line('   • Slack通知: --notify');
        $this->line('   • 完全テスト例: php artisan gogetssl:test-connection --clear-cache --domain=example.com --notify');
    }

    /**
     * トラブルシューティング情報表示
     */
    private function showTroubleshooting(): void
    {
        $this->line('');
        $this->line('🔧 トラブルシューティング:');
        $this->line('   1. .envファイルでGOGETSSL_USERNAMEとGOGETSSL_PASSWORDが設定されているか確認');
        $this->line('   2. GoGetSSLアカウントが有効で、APIアクセスが有効になっているか確認');
        $this->line('   3. --clear-cacheオプションでキャッシュをクリアして再試行');
        $this->line('   4. ネットワーク接続とファイアウォール設定を確認');
        $this->line('   5. GoGetSSL APIサーバーのステータスを確認');
    }

    /**
     * 成功通知の送信
     */
    private function sendSuccessNotification(array $testResults): void
    {
        $successCount = count(array_filter($testResults, fn($result) => $result && ($result['success'] ?? false)));
        $totalTests = count(array_filter($testResults, fn($result) => $result !== null));
        
        $this->sendSlackAlert(
            'GoGetSSL Connection Test Successful',
            'GoGetSSL API connection test completed successfully',
            [
                'Tests Passed' => "{$successCount}/{$totalTests}",
                'Connection' => $testResults['connection'] ? '✅ OK' : '❌ Failed',
                'Account Info' => $testResults['account_info']['success'] ? '✅ OK' : '❌ Failed',
                'Balance Check' => $testResults['balance']['success'] ? '✅ OK' : '❌ Failed',
                'Products List' => $testResults['products']['success'] ? '✅ OK (' . ($testResults['products']['count'] ?? 0) . ' products)' : '❌ Failed',
                'Domain Emails' => $testResults['domain_emails'] ? ($testResults['domain_emails']['success'] ? '✅ OK' : '❌ Failed') : '⏭️ Skipped'
            ],
            'success'
        );
    }

    /**
     * 失敗通知の送信
     */
    private function sendFailureNotification(\Exception $exception): void
    {
        $this->sendSlackAlert(
            'GoGetSSL Connection Test Failed',
            'GoGetSSL API connection test encountered a critical error',
            [
                'Error Message' => $exception->getMessage(),
                'Error File' => $exception->getFile() . ':' . $exception->getLine(),
                'Command' => $this->signature,
                'Action Required' => 'Check GoGetSSL service configuration and API credentials'
            ],
            'critical'
        );
    }

    /**
     * Slack通知送信
     */
    private function sendSlackAlert(string $title, string $message, array $details = [], string $severity = 'warning'): void
    {
        try {
            if (!config('services.slack.notifications.webhook_url')) {
                Log::warning('Slack webhook URL not configured, skipping GoGetSSL test alert', [
                    'title' => $title,
                    'severity' => $severity
                ]);
                return;
            }

            Notification::route('slack', config('services.slack.notifications.webhook_url'))
                ->notify(new SSLSystemAlertNotification($title, $message, $details, $severity));

            Log::info('GoGetSSL test Slack alert sent successfully', [
                'title' => $title,
                'severity' => $severity,
                'details_count' => count($details)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send GoGetSSL test Slack alert', [
                'error' => $e->getMessage(),
                'title' => $title,
                'severity' => $severity
            ]);
        }
    }
}