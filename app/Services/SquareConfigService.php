<?php

namespace App\Services;

use Square\SquareClient;
use Square\Utils\ApiResponse;
use Square\Exceptions\SquareException;

class SquareConfigService
{
    /**
     * Square Clientを正しく作成（SDK v41対応）
     */
    public static function createClient(): SquareClient
    {
        $accessToken = config('services.square.access_token');
        $environment = config('services.square.environment', 'sandbox');
        
        // 型チェック
        if (!is_string($accessToken) || empty($accessToken)) {
            throw new \InvalidArgumentException('Square access token must be a non-empty string');
        }
        
        if (!is_string($environment)) {
            throw new \InvalidArgumentException('Square environment must be a string');
        }
        
        // SDK v41の正しいコンストラクタ
        $options = [
            'timeout' => 60.0,
            'maxRetries' => 3,
            'headers' => [
                'User-Agent' => 'SSL-SaaS-Platform/1.0',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];
        
        // 環境に応じたbaseURL設定は不要（SDKが自動処理）
        if ($environment === 'production') {
            $options['baseUrl'] = 'https://connect.squareup.com';
        } else {
            $options['baseUrl'] = 'https://connect.squareupsandbox.com';
        }
        
        return new SquareClient(
            token: $accessToken,
            options: $options
        );
    }
    
    /**
     * 設定値を取得（型安全）
     */
    public static function getConfig(): array
    {
        return [
            'access_token' => config('services.square.access_token', ''),
            'application_id' => config('services.square.application_id', ''),
            'environment' => config('services.square.environment', 'sandbox'),
            'webhook_secret' => config('services.square.webhook_secret', ''),
        ];
    }
    
    /**
     * 環境チェック
     */
    public static function isProduction(): bool
    {
        return config('services.square.environment') === 'production';
    }
    
    /**
     * 設定の妥当性チェック
     */
    public static function validateConfig(): array
    {
        $config = self::getConfig();
        $errors = [];
        
        if (empty($config['access_token'])) {
            $errors[] = 'Square access token is required';
        }
        
        if (empty($config['application_id'])) {
            $errors[] = 'Square application ID is required';
        }
        
        if (!in_array($config['environment'], ['sandbox', 'production'])) {
            $errors[] = 'Square environment must be either "sandbox" or "production"';
        }
        
        return $errors;
    }
    
    /**
     * Square APIテスト（SDK v41対応）
     */
    public static function testConnection(): array
    {
        try {
            $client = self::createClient();
            
            // SDK v41では直接 ListLocationsResponse を返す
            /** @var \Square\Types\ListLocationsResponse */
            $response = $client->locations->list();
            
            // エラーチェック（errorsがあるかどうか）
            $errors = $response->getErrors();
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'message' => 'Square API connection failed',
                    'errors' => $errors
                ];
            }
            
            // 成功時はlocationsを取得
            $locations = $response->getLocations();
            
            return [
                'success' => true,
                'message' => 'Square API connection successful',
                'locations_count' => count($locations ?? [])
            ];
            
        } catch (SquareException $e) {
            return [
                'success' => false,
                'message' => 'Square API connection error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }
}