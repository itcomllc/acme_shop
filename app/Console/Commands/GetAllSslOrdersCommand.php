<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoGetSSLService;
use App\Notifications\SSLSystemAlertNotification;
use Illuminate\Support\Facades\{Log, Cache, Notification};

class GetAllSslOrdersCommand extends Command
{
    protected $signature = 'ssl:get-all-orders 
                            {--limit=100 : Number of orders to display (after filtering)}
                            {--offset=0 : Starting offset for pagination}
                            {--status= : Filter by order status (active, processing, expired, etc.)}
                            {--export= : Export to file (csv, json)}
                            {--all : Retrieve and display all orders (ignores limit)}
                            {--no-details : Skip fetching detailed information for faster execution}
                            {--debug : Output debug information for domain extraction}
                            {--notify : Send Slack notifications for issues}';

    protected $description = 'Retrieve all SSL orders from GoGetSSL';

    /** @var array<int, array{name: string, brand: string, type: string}> */
    protected array $productMap = [];

    public function __construct(
        private readonly GoGetSSLService $goGetSsl
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Starting SSL orders retrieval from GoGetSSL...');
        
        // 構造化ログ
        Log::info('SSL orders retrieval started', [
            'command' => 'ssl:get-all-orders',
            'options' => $this->options()
        ]);

        try {
            $limit = (int) $this->option('limit');
            $offset = (int) $this->option('offset');
            $status = $this->option('status');
            $exportFormat = $this->option('export');
            $retrieveAll = (bool) $this->option('all');
            $skipDetails = (bool) $this->option('no-details');
            $debug = (bool) $this->option('debug');
            $notify = (bool) $this->option('notify');

            // 最初に商品情報を取得
            $this->loadProductInformation();
            
            if ($debug) {
                $this->info("Parameters: limit={$limit}, offset={$offset}, status={$status}");
            }

            $startTime = microtime(true);
            $allOrders = $this->getOrdersBasedOnOptions(
                $retrieveAll,
                $skipDetails,
                $status,
                $limit,
                $offset,
                $debug
            );
            $processingTime = round((microtime(true) - $startTime), 2);

            if (empty($allOrders)) {
                $this->warn('❌ No SSL orders found.');
                
                // 注文が見つからない場合の通知
                if ($notify && $this->shouldNotifyNoOrders()) {
                    $this->sendSlackAlert(
                        'No SSL Orders Found',
                        'SSL orders retrieval returned no results',
                        [
                            'Status Filter' => $status ?? 'All',
                            'Offset' => $offset,
                            'Limit' => $limit,
                            'Processing Time' => "{$processingTime}s"
                        ],
                        'warning'
                    );
                }
                
                return self::SUCCESS;
            }

            // 異常検知とアラート
            $this->detectAnomaliesAndNotify($allOrders, $notify);

            $this->displayOrdersSummary($allOrders);
            $this->displayOrdersTable($allOrders);

            if ($exportFormat) {
                $this->exportOrders($allOrders, $exportFormat);
            }

            // 処理統計をキャッシュ
            $this->cacheProcessingStats($allOrders, $processingTime);

            // 構造化ログ
            Log::info('SSL orders retrieval completed successfully', [
                'orders_count' => count($allOrders),
                'processing_time' => $processingTime,
                'status_filter' => $status,
                'export_format' => $exportFormat,
                'performance_metrics' => $this->getPerformanceMetrics($allOrders, $processingTime)
            ]);

            $this->info('✅ SSL orders retrieved successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to retrieve SSL orders: ' . $e->getMessage());
            
            // 重大エラーのSlack通知
            if ($notify) {
                $this->sendSlackAlert(
                    'SSL Orders Retrieval Failed',
                    'Failed to retrieve SSL orders from GoGetSSL',
                    [
                        'Error' => $e->getMessage(),
                        'File' => $e->getFile() . ':' . $e->getLine(),
                        'Command' => $this->signature,
                        'Status Filter' => $status ?? 'All'
                    ],
                    'critical'
                );
            }
            
            Log::error('SSL orders retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'options' => $this->options()
            ]);
            
            if (config('app.debug')) {
                $this->error('Trace: ' . $e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    private function getOrdersBasedOnOptions(
        bool $retrieveAll,
        bool $skipDetails, 
        ?string $status,
        int $limit,
        int $offset,
        bool $debug
    ): array {
        if ($retrieveAll) {
            return $skipDetails 
                ? $this->getAllOrdersPaginatedFast($status)
                : $this->getAllOrdersPaginated($status, $debug);
        }

        return $this->getOrdersWithPagination($limit, $offset, $status, $skipDetails, $debug);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getOrdersWithPagination(
        int $limit,
        int $offset,
        ?string $status,
        bool $skipDetails,
        bool $debug
    ): array {
        /** @var array{orders?: array<int, array{order_id: string}>} */
        $ordersResponse = $this->goGetSsl->getAllSSLOrders(1000, $offset);
        $orderIds = array_column($ordersResponse['orders'] ?? [], 'order_id');
        
        if (empty($orderIds)) {
            return [];
        }

        // オーダーIDを分割して getOrderStatuses を呼び出し（APIの制限を考慮）
        $allCertificates = [];
        $chunkSize = 50; // 一度に50件ずつ処理
        $chunks = array_chunk($orderIds, $chunkSize);
        
        $this->info("Processing " . count($orderIds) . " orders in " . count($chunks) . " chunks...");
        
        foreach ($chunks as $chunkIndex => $chunk) {
            if ($debug) {
                $this->info("Processing chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " (" . count($chunk) . " orders)");
            }
            
            /** @var array{certificates?: array<int, array<string, mixed>>} */
            $response = $this->goGetSsl->getOrderStatuses($chunk);
            $certificates = $response['certificates'] ?? [];
            $allCertificates = array_merge($allCertificates, $certificates);
            
            // APIレート制限を考慮
            if ($chunkIndex < count($chunks) - 1) {
                usleep(100000); // 0.1秒待機
            }
        }
        
        if ($debug) {
            $this->info("getOrderStatuses returned total " . count($allCertificates) . " certificates");
            if (!empty($allCertificates)) {
                $responseOrderIds = array_column($allCertificates, 'order_id');
                if (in_array('1682136', $responseOrderIds)) {
                    $this->info("✓ Order 1682136 found in response");
                } else {
                    $this->info("✗ Order 1682136 NOT found in response");
                }
            }
        }
        
        // certificates を orders 形式に変換
        $orders = array_map(function(array $cert): array {
            return [
                'order_id' => $cert['order_id'],
                'status' => $cert['status'],
                'expires' => $cert['expires'] ?? null
            ];
        }, $allCertificates);
        
        $filteredOrders = $this->filterOrdersByStatus($orders, $status);
        
        // ユーザー指定のlimitで表示件数を制限
        if ($limit && count($filteredOrders) > $limit) {
            $filteredOrders = array_slice($filteredOrders, 0, $limit);
            $this->info("Limiting display to first {$limit} matching orders");
        }
        
        return $skipDetails 
            ? $this->createBasicOrdersData($filteredOrders)
            : $this->enrichOrdersWithDetails($filteredOrders, $debug);
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    private function createBasicOrdersData(array $orders): array
    {
        return array_map(function(array $order): array {
            return array_merge($order, [
                'domain' => 'N/A',
                'product_name' => 'N/A',
                'valid_from' => 'N/A',
                'valid_till' => $order['expires'] ?? 'N/A',
                'product_id' => 'N/A',
                'period' => 'N/A',
                'server_count' => 'N/A',
                'dcv_method' => 'N/A',
                'total_domains' => 'N/A',
                'base_domain_count' => 0,
                'single_san_count' => 0,
                'wildcard_san_count' => 0
            ]);
        }, $orders);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getAllOrdersPaginated(?string $statusFilter = null, bool $debug = false): array
    {
        // まず getAllSSLOrders で全オーダーIDを取得
        $allOrderIds = [];
        $limit = 1000;
        $offset = 0;
        
        do {
            /** @var array{orders?: array<int, array{order_id: string}>} */
            $ordersResponse = $this->goGetSsl->getAllSSLOrders($limit, $offset);
            $orders = $ordersResponse['orders'] ?? [];
            
            if (empty($orders)) {
                break;
            }
            
            $orderIds = array_column($orders, 'order_id');
            $allOrderIds = array_merge($allOrderIds, $orderIds);
            
            $offset += $limit;
            
        } while (count($orders) === $limit);
        
        if (empty($allOrderIds)) {
            return [];
        }
        
        $this->info('Processing ' . count($allOrderIds) . ' orders...');
        
        // 取得したオーダーIDで getOrderStatuses を呼び出し
        /** @var array{certificates?: array<int, array<string, mixed>>} */
        $response = $this->goGetSsl->getOrderStatuses($allOrderIds);
        $certificates = $response['certificates'] ?? [];

        // certificates を orders 形式に変換
        $orders = array_map(function(array $cert): array {
            return [
                'order_id' => $cert['order_id'],
                'status' => $cert['status'],
                'expires' => $cert['expires'] ?? null
            ];
        }, $certificates);

        $filteredOrders = $this->filterOrdersByStatus($orders, $statusFilter);
        
        return $this->enrichOrdersWithDetails($filteredOrders, $debug);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getAllOrdersPaginatedFast(?string $statusFilter = null): array
    {
        $allOrders = [];
        $limit = 1000;
        $offset = 0;
        $progressBar = null;

        do {
            /** @var array{certificates?: array<int, array<string, mixed>>} */
            $response = $this->goGetSsl->getOrderStatuses(null, $limit, $offset);
            $certificates = $response['certificates'] ?? [];
            
            if (empty($certificates)) {
                break;
            }

            if ($progressBar === null && count($certificates) > 0) {
                $progressBar = $this->output->createProgressBar(count($certificates));
                $progressBar->setFormat('Retrieving: %current%/%max% [%bar%] %percent:3s%%');
                $progressBar->start();
            }

            // certificates を orders 形式に変換
            $orders = array_map(function(array $cert): array {
                return [
                    'order_id' => $cert['order_id'],
                    'status' => $cert['status'],
                    'expires' => $cert['expires'] ?? null,
                    'valid_till' => $cert['expires'] ?? 'N/A'
                ];
            }, $certificates);

            $filteredOrders = $this->filterOrdersByStatus($orders, $statusFilter);
            $allOrders = array_merge($allOrders, $filteredOrders);
            
            if ($progressBar) {
                $progressBar->advance(count($certificates));
            }

            $offset += $limit;
            usleep(100000);

        } while (count($certificates) === $limit);

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        return $allOrders;
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    protected function enrichOrdersWithDetails(array $orders, bool $debug = false): array
    {
        if (empty($orders)) {
            return $orders;
        }

        $this->info('Fetching detailed information for each order...');
        $progressBar = $this->output->createProgressBar(count($orders));
        $progressBar->setFormat('Details: %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->start();

        $enrichedOrders = [];
        $failedOrders = 0;
        
        foreach ($orders as $order) {
            $orderId = $order['order_id'];
            $progressBar->setMessage("Order {$orderId}");
            
            try {
                /** @var array<string, mixed> */
                $orderDetails = $this->goGetSsl->getOrderStatus($orderId);
                
                // APIレスポンス全体をログに出力（特定のオーダーIDの場合）
                if (in_array($orderId, ['3048044', '3038919', '1682136']) || $debug) {
                    if ($debug) {
                        $this->info("\nFull Debug - Order {$orderId} API Response:");
                        $this->line(json_encode($orderDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
                
                if ($debug) {
                    $this->info("\nDebug - Order {$orderId} details:");
                    $this->line("Domain field: " . ($orderDetails['domain'] ?? 'empty'));
                    $this->line("Domains field: " . ($orderDetails['domains'] ?? 'empty'));
                    if (!empty($orderDetails['san'])) {
                        $this->line("SAN items: " . count($orderDetails['san']));
                        foreach ($orderDetails['san'] as $san) {
                            if (!empty($san['san_name'])) {
                                $this->line("  - SAN: " . $san['san_name']);
                            }
                        }
                    }
                    if (!empty($orderDetails['csr_code'])) {
                        $this->line("CSR available: Yes");
                    }
                }
                
                $domain = $this->extractDomainFromOrderDetails($orderDetails);
                $productName = $this->getProductName($orderDetails['product_id'] ?? null);
                
                $enrichedOrder = array_merge($order, [
                    'domain' => $domain,
                    'product_name' => $productName,
                    'valid_from' => $orderDetails['valid_from'] ?? 'N/A',
                    'valid_till' => $orderDetails['valid_till'] ?? 'N/A',
                    'product_id' => $orderDetails['product_id'] ?? 'N/A',
                    'period' => $orderDetails['ssl_period'] ?? ($orderDetails['validity_period'] ?? 'N/A'),
                    'server_count' => $orderDetails['server_count'] ?? 'N/A',
                    'dcv_method' => $orderDetails['dcv_method'] ?? 'N/A',
                    'total_domains' => $orderDetails['total_domains'] ?? 'N/A',
                    'base_domain_count' => $orderDetails['base_domain_count'] ?? 0,
                    'single_san_count' => $orderDetails['single_san_count'] ?? 0,
                    'wildcard_san_count' => $orderDetails['wildcard_san_count'] ?? 0
                ]);
                
                // SAN追加オーダーを除外（base_domain_count = 0 かつ SAN数 > 0）
                if ($enrichedOrder['base_domain_count'] == 0 && ($enrichedOrder['single_san_count'] > 0 || $enrichedOrder['wildcard_san_count'] > 0)) {
                    if ($debug) {
                        $this->warn("Skipping SAN-only order {$orderId}");
                    }
                    // 進捗バーは進めるが、結果には含めない
                    $progressBar->advance();
                    continue;
                }
                
                $enrichedOrders[] = $enrichedOrder;
                usleep(50000);
                
            } catch (\Exception $e) {
                $failedOrders++;
                
                Log::warning('Failed to fetch order details', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
                
                $enrichedOrder = array_merge($order, [
                    'domain' => 'N/A',
                    'product_name' => 'N/A',
                    'valid_from' => 'N/A',
                    'valid_till' => 'N/A',
                    'product_id' => 'N/A',
                    'period' => 'N/A',
                    'server_count' => 'N/A',
                    'dcv_method' => 'N/A',
                    'total_domains' => 'N/A',
                    'base_domain_count' => 0,
                    'single_san_count' => 0,
                    'wildcard_san_count' => 0
                ]);
                
                $enrichedOrders[] = $enrichedOrder;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // SAN追加オーダーが除外された場合の情報表示
        $excludedCount = count($orders) - count($enrichedOrders);
        if ($excludedCount > 0) {
            $this->info("Excluded {$excludedCount} SAN-only order(s) from display");
        }

        // 失敗した注文の警告
        if ($failedOrders > 0) {
            $this->warn("Failed to fetch details for {$failedOrders} order(s)");
        }

        return $enrichedOrders;
    }

    /**
     * 商品情報を事前に読み込む
     */
    protected function loadProductInformation(): void
    {
        try {
            $this->info('Loading product information...');
            /** @var array{products?: array<int, array{id: int, product?: string, brand?: string, product_type?: string}>} */
            $productsResponse = $this->goGetSsl->getSslProducts();
            
            if (!empty($productsResponse['products'])) {
                foreach ($productsResponse['products'] as $product) {
                    $this->productMap[$product['id']] = [
                        'name' => $product['product'] ?? 'Unknown',
                        'brand' => $product['brand'] ?? 'Unknown',
                        'type' => $product['product_type'] ?? 'Unknown'
                    ];
                }
                $this->info('Loaded ' . count($this->productMap) . ' products');
            } else {
                $this->warn('No products found');
            }
        } catch (\Exception $e) {
            $this->warn('Failed to load product information: ' . $e->getMessage());
            $this->productMap = [];
        }
    }

    /**
     * Product IDから商品名を取得
     */
    protected function getProductName(?int $productId): string
    {
        if (empty($productId) || !isset($this->productMap[$productId])) {
            return 'Unknown Product';
        }
        
        $product = $this->productMap[$productId];
        return $product['name'];
    }

    /**
     * @param array<string, mixed> $orderDetails
     */
    protected function extractDomainFromOrderDetails(array $orderDetails): string
    {
        $candidates = [];
        
        // 1. domain フィールドをチェック
        if (!empty($orderDetails['domain'])) {
            $candidates[] = $orderDetails['domain'];
        }
        
        // 2. domains フィールドから全てのドメインを取得
        if (!empty($orderDetails['domains'])) {
            $domains = array_map('trim', explode(',', $orderDetails['domains']));
            $candidates = array_merge($candidates, $domains);
        }
        
        // 3. SANから全てのドメインを取得
        if (!empty($orderDetails['san']) && is_array($orderDetails['san'])) {
            foreach ($orderDetails['san'] as $san) {
                if (!empty($san['san_name'])) {
                    $candidates[] = $san['san_name'];
                }
            }
        }
        
        // 4. CSRからドメインを抽出
        if (!empty($orderDetails['csr_code'])) {
            try {
                $csrDomain = $this->goGetSsl->extractDomainFromCSR($orderDetails['csr_code']);
                if ($csrDomain && $csrDomain !== 'N/A') {
                    $candidates[] = $csrDomain;
                }
            } catch (\Exception $e) {
                // CSR解析に失敗した場合は続行
            }
        }
        
        // 5. approver_method から URL を解析
        if (!empty($orderDetails['approver_method']['http']['link'])) {
            $url = $orderDetails['approver_method']['http']['link'];
            $parsed = parse_url($url);
            if (!empty($parsed['host'])) {
                $candidates[] = $parsed['host'];
            }
        }
        
        // 候補を評価
        $validDomains = [];
        $fallbackCandidates = [];
        
        foreach ($candidates as $candidate) {
            if ($this->isValidDomain($candidate)) {
                $validDomains[] = $candidate;
            } elseif (!empty($candidate) && $candidate !== 'N/A') {
                $fallbackCandidates[] = $candidate;
            }
        }
        
        // 有効なドメインがあればそれを返す
        if (!empty($validDomains)) {
            return $validDomains[0];
        }
        
        // 有効なドメインがない場合の詳細情報表示
        if (!empty($fallbackCandidates)) {
            $candidate = $fallbackCandidates[0];
            
            // 明らかに内部IDの場合は詳細情報を追加
            if (preg_match('/^[A-Z]\d+$/', $candidate)) {
                $internalId = $orderDetails['internal_id'] ?? 'N/A';
                return "Domain N/A (ID: {$candidate}, Internal: {$internalId})";
            }
            
            return $candidate . ' (ID)';
        }
        
        return 'N/A';
    }

    protected function isValidDomain(string $domain): bool
    {
        if (empty($domain) || $domain === 'N/A') {
            return false;
        }
        
        // 短すぎるもの (3文字未満) は除外
        if (strlen($domain) < 3) {
            return false;
        }
        
        // 数字のみは除外
        if (preg_match('/^\d+$/', $domain)) {
            return false;
        }
        
        // ワイルドカードドメイン: *.example.com
        if (preg_match('/^\*\.[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/', $domain)) {
            return true;
        }
        
        // 通常のドメイン: example.com
        if (preg_match('/^[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/', $domain)) {
            return true;
        }
        
        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    protected function filterOrdersByStatus(array $orders, ?string $statusFilter): array
    {
        if (!$statusFilter) {
            return $orders;
        }

        $filtered = array_filter($orders, function (array $order) use ($statusFilter): bool {
            $orderStatus = $order['status'] ?? '';
            return $orderStatus === $statusFilter;
        });
        
        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     */
    protected function displayOrdersSummary(array $orders): void
    {
        $statusCounts = [];
        $dataIssueCount = 0;
        
        foreach ($orders as $order) {
            $status = $order['status'] ?? 'unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            
            // データ問題の検知
            if (str_contains($order['domain'] ?? '', 'Domain N/A')) {
                $dataIssueCount++;
            }
        }

        $this->newLine();
        $this->info('=== SSL Orders Summary ===');
        $this->info('Total Orders: ' . count($orders));
        
        foreach ($statusCounts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }
        
        if ($dataIssueCount > 0) {
            $this->warn("  Data Issues: {$dataIssueCount}");
        }
        
        $this->newLine();
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     */
    protected function displayOrdersTable(array $orders): void
    {
        if (count($orders) > 50) {
            if (!$this->confirm('Display all ' . count($orders) . ' orders in table format?', false)) {
                $this->info('Skipping table display. Use --export option to save data.');
                return;
            }
        }

        $tableData = [];
        foreach ($orders as $order) {
            $domain = $order['domain'] ?? 'N/A';
            $productName = $order['product_name'] ?? 'N/A';
            
            // ドメイン情報が長すぎる場合は切り詰める（30文字に拡張）
            if (strlen($domain) > 30) {
                $domain = substr($domain, 0, 27) . '...';
            }
            
            // 商品名が長すぎる場合は切り詰める（60文字に拡張）
            if (strlen($productName) > 60) {
                $productName = substr($productName, 0, 57) . '...';
            }
            
            // SAN情報を構築
            $sanInfo = '';
            $singleSan = $order['single_san_count'] ?? 0;
            $wildcardSan = $order['wildcard_san_count'] ?? 0;
            
            if ($singleSan > 0 || $wildcardSan > 0) {
                $sanInfo = "S:{$singleSan} W:{$wildcardSan}";
            } else {
                $sanInfo = '-';
            }
            
            $tableData[] = [
                $order['order_id'] ?? 'N/A',
                $order['status'] ?? 'N/A',
                $domain,
                $productName,
                $sanInfo,
                $order['valid_from'] ?? 'N/A',
                $order['valid_till'] ?? 'N/A',
            ];
        }

        $this->table(
            ['Order ID', 'Status', 'Domain', 'Product', 'SAN', 'Valid From', 'Valid Till'],
            $tableData
        );
        
        // データ問題がある場合の注意事項を表示
        $hasDataIssues = false;
        foreach ($orders as $order) {
            if (str_contains($order['domain'] ?? '', 'Domain N/A')) {
                $hasDataIssues = true;
                break;
            }
        }
        
        if ($hasDataIssues) {
            $this->newLine();
            $this->warn('⚠️  Some orders have data inconsistency issues between API and Web UI.');
            $this->line('   This may require manual verification or contacting GoGetSSL support.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     */
    protected function exportOrders(array $orders, string $format): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "ssl_orders_{$timestamp}.{$format}";
        $filepath = storage_path("app/exports/{$filename}");

        $directory = dirname($filepath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        try {
            switch (strtolower($format)) {
                case 'csv':
                    $this->exportToCsv($orders, $filepath);
                    break;
                case 'json':
                    $this->exportToJson($orders, $filepath);
                    break;
                default:
                    $this->error("Unsupported export format: {$format}");
                    return;
            }

            $this->info("Orders exported to: {$filepath}");
            
            Log::info('SSL orders exported successfully', [
                'format' => $format,
                'filepath' => $filepath,
                'orders_count' => count($orders)
            ]);
            
        } catch (\Exception $e) {
            $this->error("Failed to export orders: {$e->getMessage()}");
            
            Log::error('SSL orders export failed', [
                'format' => $format,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     */
    protected function exportToCsv(array $orders, string $filepath): void
    {
        $fp = fopen($filepath, 'w');
        
        if ($fp === false) {
            throw new \RuntimeException("Cannot open file for writing: {$filepath}");
        }
        
        fputcsv($fp, [
            'Order ID',
            'Status', 
            'Domain',
            'Product Name',
            'Valid From',
            'Valid Till',
            'Product ID',
            'Period',
            'Server Count',
            'DCV Method',
            'Total Domains',
            'Base Domains',
            'Single SAN',
            'Wildcard SAN'
        ]);

        foreach ($orders as $order) {
            fputcsv($fp, [
                $order['order_id'] ?? '',
                $order['status'] ?? '',
                $order['domain'] ?? '',
                $order['product_name'] ?? '',
                $order['valid_from'] ?? '',
                $order['valid_till'] ?? '',
                $order['product_id'] ?? '',
                $order['period'] ?? '',
                $order['server_count'] ?? '',
                $order['dcv_method'] ?? '',
                $order['total_domains'] ?? '',
                $order['base_domain_count'] ?? '',
                $order['single_san_count'] ?? '',
                $order['wildcard_san_count'] ?? ''
            ]);
        }

        fclose($fp);
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     */
    protected function exportToJson(array $orders, string $filepath): void
    {
        $jsonData = [
            'exported_at' => now()->toISOString(),
            'total_orders' => count($orders),
            'orders' => $orders
        ];

        $result = file_put_contents($filepath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($result === false) {
            throw new \RuntimeException("Failed to write JSON file: {$filepath}");
        }
    }

    /**
     * 異常検知とSlack通知
     */
    private function detectAnomaliesAndNotify(array $orders, bool $notify): void
    {
        if (!$notify && !config('ssl-enhanced.monitoring.alert_on_failure', true)) {
            return;
        }

        $stats = $this->analyzeOrders($orders);

        // データ品質の問題
        if ($stats['data_issues'] > 0) {
            $this->sendSlackAlert(
                'SSL Orders Data Quality Issues',
                'Found orders with data extraction problems',
                [
                    'Orders with Issues' => $stats['data_issues'],
                    'Total Orders' => $stats['total_orders'],
                    'Issue Rate' => round(($stats['data_issues'] / max($stats['total_orders'], 1)) * 100, 1) . '%',
                    'Common Issue' => 'Domain extraction problems'
                ],
                'warning'
            );
        }

        // 失敗ステータスの大量検知
        if ($stats['failed_orders'] > 10) {
            $this->sendSlackAlert(
                'High Number of Failed SSL Orders',
                "Found {$stats['failed_orders']} failed SSL orders",
                [
                    'Failed Orders' => $stats['failed_orders'],
                    'Total Orders' => $stats['total_orders'],
                    'Failure Rate' => round(($stats['failed_orders'] / max($stats['total_orders'], 1)) * 100, 1) . '%'
                ],
                'error'
            );
        }

        // 期限切れ間近の大量検知
        $expiringSoonThreshold = 20;
        if ($stats['expiring_soon'] > $expiringSoonThreshold) {
            $this->sendSlackAlert(
                'Many SSL Orders Expiring Soon',
                "Warning: {$stats['expiring_soon']} SSL orders expiring within 30 days",
                [
                    'Expiring Soon' => $stats['expiring_soon'],
                    'Total Orders' => $stats['total_orders'],
                    'Threshold' => $expiringSoonThreshold
                ],
                'warning'
            );
        }
    }

    /**
     * 注文の分析統計
     */
    private function analyzeOrders(array $orders): array
    {
        $stats = [
            'total_orders' => count($orders),
            'data_issues' => 0,
            'failed_orders' => 0,
            'expiring_soon' => 0,
            'status_distribution' => []
        ];

        foreach ($orders as $order) {
            $status = $order['status'] ?? 'unknown';
            $stats['status_distribution'][$status] = ($stats['status_distribution'][$status] ?? 0) + 1;

            // データ問題の検知
            if (str_contains($order['domain'] ?? '', 'Domain N/A')) {
                $stats['data_issues']++;
            }

            // 失敗した注文
            if (in_array($status, ['failed', 'cancelled', 'rejected'])) {
                $stats['failed_orders']++;
            }

            // 期限切れ間近（30日以内）
            if (!empty($order['valid_till']) && $order['valid_till'] !== 'N/A') {
                try {
                    $expiryDate = new \DateTime($order['valid_till']);
                    $daysUntilExpiry = (int) $expiryDate->diff(new \DateTime())->days;
                    if ($daysUntilExpiry <= 30 && $daysUntilExpiry >= 0) {
                        $stats['expiring_soon']++;
                    }
                } catch (\Exception $e) {
                    // 日付解析エラーは無視
                }
            }
        }

        return $stats;
    }

    /**
     * 処理統計をキャッシュ
     */
    private function cacheProcessingStats(array $orders, float $processingTime): void
    {
        $stats = array_merge($this->analyzeOrders($orders), [
            'processing_time' => $processingTime,
            'last_run' => now()->toISOString(),
            'performance_category' => $this->categorizePerformance($processingTime, count($orders))
        ]);

        Cache::put('ssl_orders_retrieval_stats', $stats, now()->addHours(6));
    }

    /**
     * パフォーマンスメトリクス取得
     */
    private function getPerformanceMetrics(array $orders, float $processingTime): array
    {
        return [
            'orders_per_second' => round(count($orders) / max($processingTime, 0.1), 2),
            'processing_category' => $this->categorizePerformance($processingTime, count($orders)),
            'data_quality_score' => $this->calculateDataQualityScore($orders)
        ];
    }

    /**
     * パフォーマンスカテゴリ判定
     */
    private function categorizePerformance(float $processingTime, int $orderCount): string
    {
        $ordersPerSecond = $orderCount / max($processingTime, 0.1);
        
        if ($ordersPerSecond > 10) {
            return 'excellent';
        } elseif ($ordersPerSecond > 5) {
            return 'good';
        } elseif ($ordersPerSecond > 2) {
            return 'acceptable';
        } else {
            return 'slow';
        }
    }

    /**
     * データ品質スコア計算
     */
    private function calculateDataQualityScore(array $orders): float
    {
        if (empty($orders)) {
            return 0.0;
        }

        $validDataCount = 0;
        foreach ($orders as $order) {
            if (!str_contains($order['domain'] ?? '', 'Domain N/A') && 
                !empty($order['product_name']) && 
                $order['product_name'] !== 'N/A') {
                $validDataCount++;
            }
        }

        return round(($validDataCount / count($orders)) * 100, 1);
    }

    /**
     * Slack通知送信
     */
    private function sendSlackAlert(string $title, string $message, array $details = [], string $severity = 'warning'): void
    {
        try {
            if (!config('services.slack.notifications.webhook_url')) {
                Log::warning('Slack webhook URL not configured, skipping SSL orders alert', [
                    'title' => $title,
                    'severity' => $severity
                ]);
                return;
            }

            Notification::route('slack', config('services.slack.notifications.webhook_url'))
                ->notify(new SSLSystemAlertNotification($title, $message, $details, $severity));

            Log::info('SSL orders Slack alert sent successfully', [
                'title' => $title,
                'severity' => $severity,
                'details_count' => count($details)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send SSL orders Slack alert', [
                'error' => $e->getMessage(),
                'title' => $title,
                'severity' => $severity
            ]);
        }
    }

    /**
     * 注文が見つからない場合の通知判定
     */
    private function shouldNotifyNoOrders(): bool
    {
        // 前回の結果をチェック
        $lastStats = Cache::get('ssl_orders_retrieval_stats');
        
        // 前回結果がある場合、前回も0件だった場合は通知しない
        if ($lastStats && ($lastStats['total_orders'] ?? 0) === 0) {
            return false;
        }

        return true;
    }
}