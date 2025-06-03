<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log, Cache};

/**
 * GoGetSSL API Integration Service
 */
class GoGetSSLService
{
    private string $username;
    private string $password;
    private string $baseUrl;
    private ?string $partnerCode;
    private ?string $authKey = null;

    public function __construct()
    {
        $this->username = config('services.gogetssl.username');
        $this->password = config('services.gogetssl.password');
        $this->baseUrl = config('services.gogetssl.base_url', 'https://my.gogetssl.com/api');
        $this->partnerCode = config('services.gogetssl.partner_code');
        
        if (empty($this->username) || empty($this->password)) {
            throw new \InvalidArgumentException('GoGetSSL credentials are required');
        }
    }

    /**
     * Get authentication key with caching
     */
    private function getAuthKey(): string
    {
        if ($this->authKey) {
            return $this->authKey;
        }

        // Try to get cached auth key
        $cacheKey = 'gogetssl_auth_key_' . md5($this->username);
        $cachedAuthKey = Cache::get($cacheKey);

        if ($cachedAuthKey) {
            $this->authKey = $cachedAuthKey;
            return $this->authKey;
        }

        // Get new auth key
        try {
            $response = Http::timeout(30)
                ->contentType('application/x-www-form-urlencoded')
                ->asForm()
                ->post("{$this->baseUrl}/auth", [
                    'user' => $this->username,
                    'pass' => $this->password
                ]);

            if (!$response->successful()) {
                throw new \Exception("Authentication failed: HTTP {$response->status()}");
            }

            $data = $response->json();

            if (!isset($data['key'])) {
                throw new \Exception('Auth key not found in response: ' . json_encode($data));
            }

            $this->authKey = $data['key'];

            // Cache for 23 hours
            Cache::put($cacheKey, $this->authKey, now()->addHours(23));

            Log::info('GoGetSSL auth key obtained successfully', [
                'username' => $this->username,
                'expires_at' => now()->addHours(23)->toDateTimeString()
            ]);

            return $this->authKey;

        } catch (\Exception $e) {
            Log::error('Failed to get GoGetSSL auth key', [
                'username' => $this->username,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to authenticate with GoGetSSL: ' . $e->getMessage());
        }
    }

    /**
     * Get available SSL products
     */
    public function getProducts(): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/products", [
                'auth_key' => $this->getAuthKey()
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch products: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL products fetched successfully', [
                'success' => $data['success'] ?? false,
                'product_count' => isset($data['products']) && is_array($data['products']) ? count($data['products']) : 0
            ]);

            return $data['products'] ?? [];

        } catch (\Exception $e) {
            $this->handleApiError('Failed to fetch SSL products', $e, []);
            throw new \Exception('Failed to fetch SSL products: ' . $e->getMessage());
        }
    }

    /**
     * Create SSL certificate order
     */
    public function createOrder(array $orderData): array
    {
        // Validate required fields
        $required = ['product_id', 'period', 'csr'];
        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        try {
            // Basic parameters
            $requestData = [
                'product_id' => $orderData['product_id'],
                'period' => $orderData['period'], // months
                'csr' => $orderData['csr'],
                'server_count' => $orderData['server_count'] ?? -1,
                'webserver_type' => $orderData['webserver_type'] ?? 1,
                'dcv_method' => $orderData['dcv_method'] ?? 'email',
                'signature_hash' => $orderData['signature_hash'] ?? 'SHA2',
            ];

            // Add approver email for email DCV
            if (($orderData['dcv_method'] ?? 'email') === 'email') {
                $requestData['approver_email'] = $orderData['approver_email'] ?? '';
            }

            // Required admin fields
            $requiredAdminFields = [
                'admin_firstname',
                'admin_lastname', 
                'admin_phone',
                'admin_title',
                'admin_email'
            ];

            foreach ($requiredAdminFields as $field) {
                if (isset($orderData[$field])) {
                    $requestData[$field] = $orderData[$field];
                } else {
                    throw new \Exception("Required parameter '{$field}' is missing");
                }
            }

            // Required tech fields
            $requiredTechFields = [
                'tech_firstname',
                'tech_lastname',
                'tech_phone', 
                'tech_title',
                'tech_email'
            ];

            foreach ($requiredTechFields as $field) {
                if (isset($orderData[$field])) {
                    $requestData[$field] = $orderData[$field];
                } else {
                    throw new \Exception("Required parameter '{$field}' is missing");
                }
            }

            // Optional fields
            $optionalFields = [
                'admin_organization', 'admin_addressline1', 'admin_city', 'admin_country', 'admin_fax',
                'admin_postalcode', 'admin_region',
                'tech_organization', 'tech_city', 'tech_country', 'tech_addressline1', 'tech_fax',
                'tech_postalcode', 'tech_region',
                'dns_names', 'approver_emails', 'unique_code'
            ];

            foreach ($optionalFields as $field) {
                if (isset($orderData[$field])) {
                    $requestData[$field] = $orderData[$field];
                }
            }

            // Test mode
            if (isset($orderData['test']) && $orderData['test']) {
                $requestData['test'] = 'Y';
            }

            // Partner code
            if ($this->partnerCode) {
                $requestData['partner_order_id'] = $this->partnerCode . '_' . uniqid();
            }

            $response = Http::timeout(30)
                ->contentType('application/x-www-form-urlencoded')
                ->asForm()
                ->post("{$this->baseUrl}/orders/add_ssl_order?auth_key={$this->getAuthKey()}", $requestData);

            if (!$response->successful()) {
                throw new \Exception("Order creation failed: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL SSL order created successfully', [
                'product_id' => $orderData['product_id'],
                'period' => $orderData['period'],
                'dcv_method' => $orderData['dcv_method'] ?? 'email',
                'order_id' => $data['order_id'] ?? null,
                'test_mode' => isset($orderData['test']) && $orderData['test']
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to create SSL order', $e, [
                'product_id' => $orderData['product_id'] ?? null
            ]);
            throw new \Exception('Failed to create SSL order: ' . $e->getMessage());
        }
    }

    /**
     * Get order status
     */
    public function getOrderStatus(int $orderId): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/orders/status/{$orderId}", [
                'auth_key' => $this->getAuthKey()
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to get order status: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::debug('GoGetSSL order status retrieved', [
                'order_id' => $orderId,
                'status' => $data['status'] ?? 'unknown'
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to get order status', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to get order status: ' . $e->getMessage());
        }
    }

    /**
     * Download certificate
     */
    public function downloadCertificate(int $orderId): array
    {
        try {
            $orderStatus = $this->getOrderStatus($orderId);

            if (empty($orderStatus['crt_code'])) {
                throw new \Exception('Certificate not ready yet. Current status: ' . ($orderStatus['status'] ?? 'unknown'));
            }

            $data = [
                'certificate' => $orderStatus['crt_code'],
                'ca_bundle' => $orderStatus['ca_code'] ?? '',
                'order_status' => $orderStatus['status'],
                'valid_from' => $orderStatus['valid_from'] ?? null,
                'valid_till' => $orderStatus['valid_till'] ?? null
            ];

            Log::info('GoGetSSL certificate downloaded', [
                'order_id' => $orderId,
                'has_certificate' => !empty($data['certificate']),
                'has_ca_bundle' => !empty($data['ca_bundle']),
                'status' => $orderStatus['status']
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to download certificate', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to download certificate: ' . $e->getMessage());
        }
    }

    /**
     * Resend validation email
     */
    public function resendValidationEmail(int $orderId): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/orders/ssl/resend_validation_email/{$orderId}", [
                'auth_key' => $this->getAuthKey()
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to resend validation email: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL validation email resent', [
                'order_id' => $orderId,
                'message' => $data['message'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to resend validation email', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to resend validation email: ' . $e->getMessage());
        }
    }

    /**
     * Revoke certificate
     */
    public function revokeCertificate(int $orderId, string $reason = 'unspecified'): array
    {
        try {
            $response = Http::timeout(30)
                ->contentType('application/x-www-form-urlencoded')
                ->asForm()
                ->post("{$this->baseUrl}/orders/cancel_ssl_order?auth_key={$this->getAuthKey()}", [
                    'order_id' => $orderId,
                    'reason' => $reason
                ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to revoke certificate: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL certificate revoked', [
                'order_id' => $orderId,
                'reason' => $reason,
                'success' => $data['success'] ?? false
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to revoke certificate', $e, [
                'order_id' => $orderId,
                'reason' => $reason
            ]);
            throw new \Exception('Failed to revoke certificate: ' . $e->getMessage());
        }
    }

    /**
     * Get domain approval emails
     */
    public function getDomainEmails(string $domain): array
    {
        try {
            $response = Http::timeout(30)
                ->contentType('application/x-www-form-urlencoded')
                ->asForm()
                ->post("{$this->baseUrl}/tools/domain/emails?auth_key=" . $this->getAuthKey(), [
                    'domain' => $domain
                ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to get domain emails: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL domain emails fetched', [
                'domain' => $domain,
                'success' => $data['success'] ?? false,
                'comodo_emails' => isset($data['ComodoApprovalEmails']) ? count($data['ComodoApprovalEmails']) : 0,
                'geotrust_emails' => isset($data['GeotrustApprovalEmails']) ? count($data['GeotrustApprovalEmails']) : 0
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to get domain emails', $e, [
                'domain' => $domain
            ]);
            throw new \Exception('Failed to get domain emails: ' . $e->getMessage());
        }
    }

    /**
     * Get unified approval emails for domain
     */
    public function getApprovalEmails(string $domain): array
    {
        $response = $this->getDomainEmails($domain);

        if (!isset($response['success']) || !$response['success']) {
            return [];
        }

        // Merge Comodo and Geotrust email addresses
        $emails = [];

        if (isset($response['ComodoApprovalEmails'])) {
            $emails = array_merge($emails, $response['ComodoApprovalEmails']);
        }

        if (isset($response['GeotrustApprovalEmails'])) {
            $emails = array_merge($emails, $response['GeotrustApprovalEmails']);
        }

        // Remove duplicates and return
        return array_unique($emails);
    }

    /**
     * Get account information
     */
    public function getAccountInfo(): array
    {
        try {
            $response = Http::timeout(30)
                ->contentType('application/x-www-form-urlencoded')
                ->asForm()
                ->post("{$this->baseUrl}/account?auth_key={$this->getAuthKey()}", [
                ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to get account info: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL account info retrieved successfully');

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to get account info', $e, []);
            throw new \Exception('Failed to get account info: ' . $e->getMessage());
        }
    }

    /**
     * Get account balance
     */
    public function getBalance(): array
    {
        try {
            $response = Http::timeout(30)
                ->contentType('application/x-www-form-urlencoded')
                ->asForm()
                ->post("{$this->baseUrl}/account/balance?auth_key={$this->getAuthKey()}", [
                ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to get balance: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL balance retrieved', [
                'balance' => $data['balance'] ?? 'unknown'
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->handleApiError('Failed to get balance', $e, []);
            throw new \Exception('Failed to get balance: ' . $e->getMessage());
        }
    }

    /**
     * 全てのSSL注文を取得
     * @param int|null $limit 取得する件数（nullの場合は制限なし）
     * @param int|null $offset オフセット（nullの場合は0）
     * @return array 全てのSSL注文の情報
     */
    public function getAllSSLOrders($limit = null, $offset = null)
    {
        try {

            if ($limit !== null) {
                $queryParams['limit'] = $limit;
            }

            if ($offset !== null) {
                $queryParams['offset'] = $offset;
            }

            $response = Http::timeout(30)->get("{$this->baseUrl}/orders/ssl/all", [
                'auth_key' => $this->getAuthKey()
            ] + $queryParams);

            $data = $response->json();

            Log::info('GoGetSSL all SSL orders retrieved', [
                'limit' => $data['limit'] ?? $limit,
                'offset' => $data['offset'] ?? $offset,
                'count' => $data['count'] ?? 0,
                'orders_returned' => isset($data['orders']) ? count($data['orders']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (\Exception $e) {
            $this->handleApiError('Failed to get all SSL orders', $e, [
                'limit' => $limit,
                'offset' => $offset
            ]);
            throw new \Exception('Failed to get all SSL orders: ' . $e->getMessage());
        }
    }

    /**
     * 注文のステータスを取得
     * @param array|string|null $orderIds 注文IDの配列または単一の注文ID（nullの場合は全ての注文を取得）
     * @param int|null $limit 取得する件数（nullの場合は制限なし）
     * @param int|null $offset オフセット（nullの場合は0）
     * @return array 注文ステータスの情報
     */
    public function getOrderStatuses($orderIds = null, $limit = null, $offset = null)
    {
        try {
            $queryParams = [
                'auth_key' => $this->getAuthKey()
            ];

            if ($limit !== null) {
                $queryParams['limit'] = $limit;
            }

            if ($offset !== null) {
                $queryParams['offset'] = $offset;
            }

            $formParams = [];

            if ($orderIds !== null) {
                if (is_array($orderIds)) {
                    $formParams['cids'] = implode(',', $orderIds);
                } else {
                    $formParams['cids'] = $orderIds;
                }
            }

            $response = Http::timeout(30)
                ->contentType('application/x-www-form-urlencoded')
                ->asForm()
                ->post("{$this->baseUrl}/orders/statuses?auth_key={$this->getAuthKey()}", $formParams);

            if (!$response->successful()) {
                throw new \Exception("Failed to get balance: HTTP {$response->status()}");
            }

            $data = $response->json();

            Log::info('GoGetSSL order statuses retrieved', [
                'order_ids_filter' => $orderIds ? (is_array($orderIds) ? implode(',', $orderIds) : $orderIds) : 'all',
                'limit' => $limit,
                'offset' => $offset,
                'certificates_count' => isset($data['certificates']) ? count($data['certificates']) : 0,
                'success' => $data['success'] ?? false,
                'time_stamp' => $data['time_stamp'] ?? null
            ]);

            return $data;
        } catch (\Exception $e) {
            $this->handleApiError('Failed to get order statuses', $e, [
                'order_ids_filter' => $orderIds ? (is_array($orderIds) ? implode(',', $orderIds) : $orderIds) : 'all',
                'limit' => $limit,
                'offset' => $offset
            ]);
            throw new \Exception('Failed to get order statuses: ' . $e->getMessage());
        }
    }

   /**
     * SSL商品一覧を取得
     */
    public function getSslProducts()
    {
        try {
            
            $response = Http::timeout(30)->get("{$this->baseUrl}/products/ssl", [
                'auth_key' => $this->getAuthKey()
            ]);

            $data = $response->json();

            Log::info('GoGetSSL SSL products retrieved', [
                'products_count' => isset($data['products']) ? count($data['products']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (\Exception $e) {
            $this->handleApiError('Failed to get SSL products', $e, []);
            throw new \Exception('Failed to get SSL products: ' . $e->getMessage());
        }
    }

    /**
     * CSRからドメイン名を抽出
     */
    public function extractDomainFromCSR($csr)
    {
        try {
            // CSRの形式を正規化
            $csr = trim($csr);
            if (!str_starts_with($csr, '-----BEGIN')) {
                $csr = "-----BEGIN CERTIFICATE REQUEST-----\n" .
                    chunk_split($csr, 64, "\n") .
                    "-----END CERTIFICATE REQUEST-----";
            }

            $csrResource = openssl_csr_get_subject($csr);

            if ($csrResource && isset($csrResource['CN'])) {
                Log::debug('Domain extracted from CSR', [
                    'domain' => $csrResource['CN']
                ]);
                return $csrResource['CN'];
            }

            throw new \Exception('Common Name (CN) not found in CSR');
        } catch (\Exception $e) {
            Log::error('Failed to extract domain from CSR', [
                'error' => $e->getMessage(),
                'csr_length' => strlen($csr)
            ]);

            throw new \Exception('Unable to extract domain from CSR: ' . $e->getMessage());
        }
    }

    /**
     * Clear auth key cache
     */
    public function clearAuthCache(): void
    {
        $cacheKey = 'gogetssl_auth_key_' . md5($this->username);
        Cache::forget($cacheKey);
        $this->authKey = null;

        Log::info('GoGetSSL auth key cache cleared');
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        try {
            $this->getAuthKey();
            $account = $this->getAccountInfo();
            
            return [
                'success' => true,
                'message' => 'GoGetSSL API connection successful',
                'account' => $account
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'GoGetSSL API connection failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle API errors
     */
    private function handleApiError(string $message, \Exception $e, array $context): void
    {
        // Clear auth cache if authentication error
        if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), '403')) {
            $this->clearAuthCache();
        }

        Log::error($message, array_merge($context, [
            'error' => $e->getMessage()
        ]));
    }
}