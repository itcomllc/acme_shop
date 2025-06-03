<?php

namespace App\Console\Commands;

use App\Services\GoGetSSLService;
use Illuminate\Console\Command;

class TestGoGetSSLConnectionCommand extends Command
{
    protected $signature = 'gogetssl:test-connection {--clear-cache : Clear auth cache before testing} {--domain= : Test domain email retrieval}';
    protected $description = 'Test GoGetSSL API connection';

    public function handle(): int
    {
        $this->info('GoGetSSL API接続をテストしています...');

        try {
            /** @var GoGetSSLService */
            $service = app(GoGetSSLService::class);
            
            // キャッシュクリアオプション
            if ($this->option('clear-cache')) {
                $service->clearAuthCache();
                $this->line('🧹 認証キャッシュをクリアしました');
            }
            
            // 接続テスト
            $connectionResult = $service->testConnection();
            if (!$connectionResult['success']) {
                $this->error('❌ 接続テストに失敗しました: ' . ($connectionResult['error'] ?? 'Unknown error'));
                return self::FAILURE;
            }
            
            $this->info('✅ 認証成功 - Auth key取得完了');
            
            // アカウント情報取得
            try {
                $accountInfo = $service->getAccountInfo();
                $this->line('📋 アカウント情報:');
                $this->line('   名前: ' . ($accountInfo['first_name'] ?? 'N/A') . ' ' . ($accountInfo['last_name'] ?? 'N/A'));
                $this->line('   会社名: ' . ($accountInfo['company_name'] ?? 'N/A'));
                $this->line('   メール: ' . ($accountInfo['email'] ?? 'N/A'));
                $this->line('   国: ' . ($accountInfo['country'] ?? 'N/A'));
                $this->line('   通貨: ' . ($accountInfo['currency'] ?? 'N/A'));
                if (isset($accountInfo['reseller_plan'])) {
                    $this->line('   リセラープラン: ' . ($accountInfo['reseller_plan'] ?? 'なし'));
                }
            } catch (\Exception $e) {
                $this->warn('⚠️  アカウント情報取得に失敗: ' . $e->getMessage());
            }
            
            // 残高取得
            try {
                $balance = $service->getBalance();
                $this->line('💰 残高: ' . ($balance['balance'] ?? 'N/A'));
                $this->line('   通貨: ' . ($balance['currency'] ?? 'N/A'));
            } catch (\Exception $e) {
                $this->warn('⚠️  残高取得に失敗: ' . $e->getMessage());
            }
            
            // 商品一覧取得
            try {
                $products = $service->getProducts();
                $this->line('📦 利用可能商品数: ' . count($products));
                
                if (count($products) > 0) {
                    $this->line('   主要商品:');
                    foreach (array_slice($products, 0, 3) as $product) {
                        // 実際のAPIレスポンス構造に合わせて調整
                        $productName = $product['product'] ?? $product['name'] ?? 'Unknown';
                        $productId = $product['product_id'] ?? $product['id'] ?? 'N/A';
                        $brand = $product['brand'] ?? 'N/A';
                        
                        $this->line("   - {$productName} (ID: {$productId}, ブランド: {$brand})");
                    }
                }
            } catch (\Exception $e) {
                $this->warn('⚠️  商品一覧取得に失敗: ' . $e->getMessage());
            }

            // ドメインメール取得テスト（オプション）
            if ($domain = $this->option('domain')) {
                try {
                    $this->line('');
                    $this->line("📧 ドメイン「{$domain}」の承認メール一覧:");
                    
                    $domainEmails = $service->getDomainEmails($domain);
                    
                    if (isset($domainEmails['success']) && $domainEmails['success']) {
                        if (isset($domainEmails['ComodoApprovalEmails']) && is_array($domainEmails['ComodoApprovalEmails'])) {
                            $this->line('   🔹 Comodo承認メール (' . count($domainEmails['ComodoApprovalEmails']) . '件):');
                            foreach ($domainEmails['ComodoApprovalEmails'] as $email) {
                                $this->line("     - {$email}");
                            }
                        }
                        
                        if (isset($domainEmails['GeotrustApprovalEmails']) && is_array($domainEmails['GeotrustApprovalEmails'])) {
                            $this->line('   🔹 Geotrust承認メール (' . count($domainEmails['GeotrustApprovalEmails']) . '件):');
                            foreach ($domainEmails['GeotrustApprovalEmails'] as $email) {
                                $this->line("     - {$email}");
                            }
                        }

                        // 統合版も表示
                        $approvalEmails = $service->getApprovalEmails($domain);
                        if (!empty($approvalEmails)) {
                            $this->line('   📋 統合承認メール一覧 (' . count($approvalEmails) . '件):');
                            foreach ($approvalEmails as $email) {
                                $this->line("     - {$email}");
                            }
                        } else {
                            $this->line('   📋 承認メールが見つかりませんでした');
                        }
                    } else {
                        $this->warn('   ⚠️  ドメインメール取得に失敗しました');
                        if (isset($domainEmails['error'])) {
                            $this->warn('   エラー: ' . $domainEmails['error']);
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("   ⚠️  ドメイン「{$domain}」のメール取得に失敗: " . $e->getMessage());
                }
            }

            // 使用方法のヒント
            $this->line('');
            $this->line('💡 使用方法のヒント:');
            $this->line('   • キャッシュクリア: --clear-cache');
            $this->line('   • ドメインテスト: --domain=example.com');
            $this->line('   • 完全テスト例: php artisan gogetssl:test-connection --clear-cache --domain=example.com');
            
            $this->info('🎉 GoGetSSL API接続テスト完了');
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ GoGetSSL API接続エラー: ' . $e->getMessage());
            $this->line('');
            $this->line('🔧 トラブルシューティング:');
            $this->line('   1. .envファイルでGOGETSSL_USERNAMEとGOGETSSL_PASSWORDが設定されているか確認');
            $this->line('   2. GoGetSSLアカウントが有効で、APIアクセスが有効になっているか確認');
            $this->line('   3. --clear-cacheオプションでキャッシュをクリアして再試行');
            
            return self::FAILURE;
        }
    }
}