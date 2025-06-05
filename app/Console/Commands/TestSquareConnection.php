<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SquareConfigService;
use App\Notifications\SSLSystemAlertNotification;
use Illuminate\Support\Facades\{Log, Cache, Notification};

class TestSquareConnection extends Command
{
    protected $signature = 'square:test-connection 
                            {--clear-cache : Clear Square API cache before testing}
                            {--create-test-product : Create a test product (sandbox only)}
                            {--list-locations : List all available locations}
                            {--test-payments : Test payment processing capabilities}
                            {--silent : Minimal output mode}
                            {--notify : Send Slack notifications on failures}';
    
    protected $description = 'Test Square API connection and functionality';

    public function handle(): int
    {
        if (!$this->option('silent')) {
            $this->info('🔍 Square API接続をテストしています...');
        }

        // 構造化ログ
        Log::info('Square API connection test started', [
            'command' => 'square:test-connection',
            'options' => $this->options(),
            'environment' => config('services.square.environment')
        ]);

        try {
            // キャッシュクリアオプション
            if ($this->option('clear-cache')) {
                $this->clearSquareCache();
                if (!$this->option('silent')) {
                    $this->line('🧹 Square APIキャッシュをクリアしました');
                }
            }
            
            // 基本接続テスト
            $connectionResult = $this->testBasicConnection();
            if (!$connectionResult['success']) {
                return self::FAILURE;
            }
            
            // ロケーション情報取得テスト
            $locationsResult = $this->testLocations();
            
            // アプリケーション情報取得テスト
            $applicationResult = $this->testApplicationInfo();
            
            // 支払いプランテスト
            $plansResult = $this->testPaymentPlans();
            
            // 商品作成テスト（オプション、サンドボックスのみ）
            $productResult = null;
            if ($this->option('create-test-product') && $this->isSandboxEnvironment()) {
                $productResult = $this->testProductCreation();
            }
            
            // 支払い処理テスト（オプション）
            $paymentResult = null;
            if ($this->option('test-payments')) {
                $paymentResult = $this->testPaymentProcessing();
            }
            
            // 使用方法のヒント表示
            if (!$this->option('silent')) {
                $this->showUsageHints();
            }
            
            // テスト結果の総括
            $testResults = [
                'connection' => $connectionResult,
                'locations' => $locationsResult,
                'application' => $applicationResult,
                'payment_plans' => $plansResult,
                'product_creation' => $productResult,
                'payment_processing' => $paymentResult
            ];
            
            // 成功通知
            if ($this->option('notify') && config('services.slack.notifications.webhook_url')) {
                $this->sendSuccessNotification($testResults);
            }
            
            if (!$this->option('silent')) {
                $this->info('🎉 Square API接続テスト完了');
            }
            
            Log::info('Square API connection test completed successfully', [
                'test_time' => now()->toISOString(),
                'results' => $testResults,
                'environment' => config('services.square.environment')
            ]);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Square API接続エラー: ' . $e->getMessage());
            
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
            
            Log::error('Square API connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => $this->options(),
                'environment' => config('services.square.environment')
            ]);
            
            return self::FAILURE;
        }
    }

    /**
     * 基本接続テスト
     */
    private function testBasicConnection(): array
    {
        try {
            $connectionResult = SquareConfigService::testConnection();
            
            if (!$connectionResult['success']) {
                $this->error('❌ 接続テストに失敗しました: ' . ($connectionResult['message'] ?? 'Unknown error'));
                
                if (isset($connectionResult['errors'])) {
                    foreach ($connectionResult['errors'] as $error) {
                        $this->error('   - ' . ($error['detail'] ?? 'Unknown error'));
                    }
                }
                
                if ($this->option('notify')) {
                    $this->sendSlackAlert(
                        'Square API Connection Failed',
                        'Basic connection test to Square API failed',
                        [
                            'Error' => $connectionResult['message'] ?? 'Unknown error',
                            'Environment' => config('services.square.environment'),
                            'Application ID' => config('services.square.application_id')
                        ],
                        'critical'
                    );
                }
                
                return $connectionResult;
            }
            
            if (!$this->option('silent')) {
                $this->info('✅ ' . $connectionResult['message']);
                if (isset($connectionResult['locations_count'])) {
                    $this->info('📍 Found ' . $connectionResult['locations_count'] . ' location(s)');
                }
            }
            
            return $connectionResult;
            
        } catch (\Exception $e) {
            $this->error('❌ 接続テスト中にエラーが発生: ' . $e->getMessage());
            
            if ($this->option('notify')) {
                $this->sendSlackAlert(
                    'Square API Connection Exception',
                    'Exception occurred during Square API connection test',
                    [
                        'Exception' => $e->getMessage(),
                        'File' => $e->getFile() . ':' . $e->getLine(),
                        'Environment' => config('services.square.environment')
                    ],
                    'critical'
                );
            }
            
            throw $e;
        }
    }

    /**
     * ロケーション情報取得テスト
     */
    private function testLocations(): array
    {
        try {
            if (!$this->option('list-locations') && !$this->option('verbose')) {
                return ['success' => true, 'skipped' => true];
            }

            if (!$this->option('silent')) {
                $this->line('📍 ロケーション情報を取得中...');
            }
            
            $client = SquareConfigService::createClient();
            /** @var \Square\Models\ListLocationsResponse */
            $response = $client->locations->list();
            
            if ($response->getErrors()) {
                $errors = [];
                foreach ($response->getErrors() as $error) {
                    $errors[] = $error->getDetail();
                }
                
                if (!$this->option('silent')) {
                    $this->warn('⚠️  ロケーション情報取得に失敗: ' . implode(', ', $errors));
                }
                
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            $locations = $response->getLocations() ?? [];
            $locationCount = count($locations);
            
            if (!$this->option('silent')) {
                $this->line("📋 利用可能ロケーション数: {$locationCount}");
                
                if ($this->option('list-locations') && $locationCount > 0) {
                    $this->line('   詳細ロケーション情報:');
                    foreach ($locations as $location) {
                        $name = $location->getName() ?? 'N/A';
                        $id = $location->getId() ?? 'N/A';
                        $status = $location->getStatus() ?? 'N/A';
                        $type = $location->getType() ?? 'N/A';
                        
                        $this->line("   - {$name} (ID: {$id})");
                        $this->line("     ステータス: {$status}, タイプ: {$type}");
                        
                        if ($location->getAddress()) {
                            $address = $location->getAddress();
                            $addressLine = $address->getAddressLine1() ?? '';
                            $locality = $address->getLocality() ?? '';
                            if ($addressLine || $locality) {
                                $this->line("     住所: {$addressLine}, {$locality}");
                            }
                        }
                    }
                }
            }
            
            // ロケーション数の異常検知
            if ($locationCount === 0) {
                $this->warn('⚠️  ロケーションが見つかりません。Square アカウントの設定を確認してください。');
                
                if ($this->option('notify')) {
                    $this->sendSlackAlert(
                        'Square No Locations Found',
                        'No locations found in Square account',
                        [
                            'Location Count' => $locationCount,
                            'Environment' => config('services.square.environment'),
                            'Possible Issue' => 'Account setup incomplete or permissions issue'
                        ],
                        'warning'
                    );
                }
            }
            
            return [
                'success' => true,
                'locations_count' => $locationCount,
                'locations' => array_map(function($location) {
                    return [
                        'id' => $location->getId(),
                        'name' => $location->getName(),
                        'status' => $location->getStatus(),
                        'type' => $location->getType()
                    ];
                }, $locations)
            ];
            
        } catch (\Exception $e) {
            if (!$this->option('silent')) {
                $this->warn('⚠️  ロケーション情報取得に失敗: ' . $e->getMessage());
            }
            
            if ($this->option('notify')) {
                $this->sendSlackAlert(
                    'Square Locations Fetch Failed',
                    'Failed to retrieve locations from Square API',
                    [
                        'Error' => $e->getMessage(),
                        'Function' => 'listLocations'
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
     * アプリケーション情報取得テスト
     */
    private function testApplicationInfo(): array
    {
        try {
            if (!$this->option('silent')) {
                $this->line('📱 アプリケーション情報を確認中...');
            }
            
            $applicationId = config('services.square.application_id');
            $environment = config('services.square.environment');
            $accessToken = config('services.square.access_token');
            
            if (!$this->option('silent')) {
                $this->line('   アプリケーションID: ' . ($applicationId ? 'Set' : 'Not Set'));
                $this->line('   環境: ' . $environment);
                $this->line('   アクセストークン: ' . ($accessToken ? 'Set' : 'Not Set'));
            }
            
            // 設定の検証
            $configIssues = [];
            if (!$applicationId) {
                $configIssues[] = 'Application ID not configured';
            }
            if (!$accessToken) {
                $configIssues[] = 'Access Token not configured';
            }
            if (!in_array($environment, ['sandbox', 'production'])) {
                $configIssues[] = 'Invalid environment setting';
            }
            
            if (!empty($configIssues)) {
                if (!$this->option('silent')) {
                    $this->warn('⚠️  設定に問題があります:');
                    foreach ($configIssues as $issue) {
                        $this->warn("   - {$issue}");
                    }
                }
                
                return [
                    'success' => false,
                    'issues' => $configIssues
                ];
            }
            
            return [
                'success' => true,
                'application_id' => $applicationId,
                'environment' => $environment,
                'access_token_configured' => !empty($accessToken)
            ];
            
        } catch (\Exception $e) {
            if (!$this->option('silent')) {
                $this->warn('⚠️  アプリケーション情報確認に失敗: ' . $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 支払いプラン設定テスト
     */
    private function testPaymentPlans(): array
    {
        try {
            if (!$this->option('silent')) {
                $this->line('💳 支払いプラン設定を確認中...');
            }
            
            $plans = [
                'basic' => config('services.square.plan_basic'),
                'professional' => config('services.square.plan_professional'),
                'enterprise' => config('services.square.plan_enterprise')
            ];
            
            $configuredPlans = 0;
            $planDetails = [];
            
            foreach ($plans as $planName => $planId) {
                $isConfigured = !empty($planId);
                if ($isConfigured) {
                    $configuredPlans++;
                }
                
                $planDetails[$planName] = [
                    'configured' => $isConfigured,
                    'plan_id' => $planId
                ];
                
                if (!$this->option('silent')) {
                    $status = $isConfigured ? '✅' : '❌';
                    $this->line("   {$status} {$planName}: " . ($isConfigured ? $planId : 'Not Set'));
                }
            }
            
            // プラン設定の警告
            if ($configuredPlans === 0) {
                $this->warn('⚠️  支払いプランが設定されていません。サブスクリプション機能が利用できません。');
                
                if ($this->option('notify')) {
                    $this->sendSlackAlert(
                        'Square Payment Plans Not Configured',
                        'No Square payment plans are configured for subscriptions',
                        [
                            'Configured Plans' => $configuredPlans,
                            'Total Plans' => count($plans),
                            'Impact' => 'Subscription functionality unavailable'
                        ],
                        'warning'
                    );
                }
            } elseif ($configuredPlans < count($plans)) {
                $this->warn("⚠️  一部の支払いプランが未設定です ({$configuredPlans}/" . count($plans) . ")");
            }
            
            return [
                'success' => true,
                'configured_plans' => $configuredPlans,
                'total_plans' => count($plans),
                'plan_details' => $planDetails
            ];
            
        } catch (\Exception $e) {
            if (!$this->option('silent')) {
                $this->warn('⚠️  支払いプラン確認に失敗: ' . $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 商品作成テスト（サンドボックスのみ）
     */
    private function testProductCreation(): array
    {
        try {
            if (!$this->isSandboxEnvironment()) {
                if (!$this->option('silent')) {
                    $this->warn('⚠️  商品作成テストは本番環境では実行できません');
                }
                return ['success' => false, 'reason' => 'Not available in production'];
            }
            
            if (!$this->option('silent')) {
                $this->line('🛍️  Catalog情報を確認中...');
            }
            
            $client = SquareConfigService::createClient();
            /** @var \Square\Types\CatalogInfoResponse */
            $response = $client->catalog->info();
            
            if (!$response) {
                return [
                    'success' => false,
                    'error' => 'Catalog API not available'
                ];
            }
            
            if (!$this->option('silent')) {
                $this->line('   ✅ Catalog API利用可能');
                $this->line('   ℹ️  実際の商品作成はSquareConfigServiceで実装可能');
            }
            
            return [
                'success' => true,
                'catalog_api_available' => true,
                'note' => 'Catalog API capability verified'
            ];
            
        } catch (\Exception $e) {
            if (!$this->option('silent')) {
                $this->warn('⚠️  Catalog確認に失敗: ' . $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 支払い処理テスト
     */
    private function testPaymentProcessing(): array
    {
        try {
            if (!$this->option('silent')) {
                $this->line('💰 支払い処理機能を確認中...');
            }
            
            $client = SquareConfigService::createClient();
            /** @var Square\Core\Pagination\Pager<\Square\Types\Payment> */
            $response = $client->payments->list();
            
            if (!$response) {
                return [
                    'success' => false,
                    'error' => 'Payments API not available'
                ];
            }
            
            if (!$this->option('silent')) {
                $this->line('   ✅ Payments API利用可能');
                if ($this->isSandboxEnvironment()) {
                    $this->line('   ℹ️  サンドボックス環境: テスト支払いが可能');
                } else {
                    $this->line('   ⚠️  本番環境: 実際の支払い処理が実行されます');
                }
            }
            
            return [
                'success' => true,
                'payments_api_available' => true,
                'environment' => config('services.square.environment'),
                'test_payments_safe' => $this->isSandboxEnvironment()
            ];
            
        } catch (\Exception $e) {
            if (!$this->option('silent')) {
                $this->warn('⚠️  支払い処理確認に失敗: ' . $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * サンドボックス環境かチェック
     */
    private function isSandboxEnvironment(): bool
    {
        return config('services.square.environment') === 'sandbox';
    }

    /**
     * Square APIキャッシュをクリア
     */
    private function clearSquareCache(): void
    {
        // Square関連のキャッシュをクリア
        $cacheKeys = [
            'square_locations',
            'square_application_info',
            'square_catalog_items',
            'square_connection_test'
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
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
        $this->line('   • ロケーション一覧: --list-locations');
        $this->line('   • 商品作成テスト: --create-test-product (sandbox only)');
        $this->line('   • 支払い処理テスト: --test-payments');
        $this->line('   • 詳細出力: --verbose');
        $this->line('   • 静寂モード: --silent');
        $this->line('   • Slack通知: --notify');
        $this->line('   • 完全テスト例: php artisan square:test-connection --clear-cache --list-locations --test-payments --notify');
    }

    /**
     * トラブルシューティング情報表示
     */
    private function showTroubleshooting(): void
    {
        $this->line('');
        $this->line('🔧 トラブルシューティング:');
        $this->line('   1. .envファイルでSQUARE_APPLICATION_IDとSQUARE_ACCESS_TOKENが設定されているか確認');
        $this->line('   2. SQUARE_ENVIRONMENTが正しく設定されているか確認 (sandbox/production)');
        $this->line('   3. Square Developer Dashboardでアプリケーションが有効になっているか確認');
        $this->line('   4. アクセストークンの権限が適切に設定されているか確認');
        $this->line('   5. ネットワーク接続とファイアウォール設定を確認');
        $this->line('   6. Square APIサーバーのステータスを確認');
    }

    /**
     * 成功通知の送信
     */
    private function sendSuccessNotification(array $testResults): void
    {
        $successCount = count(array_filter($testResults, function($result) {
            return $result && ($result['success'] ?? false);
        }));
        $totalTests = count(array_filter($testResults, fn($result) => $result !== null));
        
        $this->sendSlackAlert(
            'Square API Connection Test Successful',
            'Square API connection test completed successfully',
            [
                'Tests Passed' => "{$successCount}/{$totalTests}",
                'Environment' => config('services.square.environment'),
                'Connection' => $testResults['connection']['success'] ? '✅ OK' : '❌ Failed',
                'Locations' => isset($testResults['locations']['success']) ? 
                    ($testResults['locations']['success'] ? '✅ OK (' . ($testResults['locations']['locations_count'] ?? 0) . ' locations)' : '❌ Failed') : '⏭️ Skipped',
                'Application Info' => $testResults['application']['success'] ? '✅ OK' : '❌ Failed',
                'Payment Plans' => $testResults['payment_plans']['success'] ? 
                    '✅ OK (' . ($testResults['payment_plans']['configured_plans'] ?? 0) . ' configured)' : '❌ Failed',
                'Product Creation' => $testResults['product_creation'] ? 
                    ($testResults['product_creation']['success'] ? '✅ OK' : '❌ Failed') : '⏭️ Skipped',
                'Payment Processing' => $testResults['payment_processing'] ? 
                    ($testResults['payment_processing']['success'] ? '✅ OK' : '❌ Failed') : '⏭️ Skipped'
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
            'Square API Connection Test Failed',
            'Square API connection test encountered a critical error',
            [
                'Error Message' => $exception->getMessage(),
                'Error File' => $exception->getFile() . ':' . $exception->getLine(),
                'Environment' => config('services.square.environment'),
                'Command' => $this->signature,
                'Action Required' => 'Check Square API configuration and credentials'
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
                Log::warning('Slack webhook URL not configured, skipping Square test alert', [
                    'title' => $title,
                    'severity' => $severity
                ]);
                return;
            }

            Notification::route('slack', config('services.slack.notifications.webhook_url'))
                ->notify(new SSLSystemAlertNotification($title, $message, $details, $severity));

            Log::info('Square test Slack alert sent successfully', [
                'title' => $title,
                'severity' => $severity,
                'details_count' => count($details)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Square test Slack alert', [
                'error' => $e->getMessage(),
                'title' => $title,
                'severity' => $severity
            ]);
        }
    }
}