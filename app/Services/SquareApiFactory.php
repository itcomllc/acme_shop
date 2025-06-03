<?php

namespace App\Services;

use Square\SquareClient;

/**
 * Square API Factory - SDK v41対応
 * 注意: SDK v41では直接 $client->payments, $client->locations などにアクセス可能
 * このファクトリークラスは互換性とテスタビリティのために提供
 */
class SquareApiFactory
{
    public function __construct(
        private readonly SquareClient $client
    ) {}

    /**
     * Payments APIアクセス
     * @return \Square\Payments\PaymentsClient
     */
    public function payments()
    {
        return $this->client->payments;
    }

    /**
     * Locations APIアクセス
     * @return \Square\Locations\LocationsClient
     */
    public function locations()
    {
        return $this->client->locations;
    }

    /**
     * Customers APIアクセス
     * @return \Square\Customers\CustomersClient
     */
    public function customers()
    {
        return $this->client->customers;
    }

    /**
     * Orders APIアクセス
     * @return \Square\Orders\OrdersClient
     */
    public function orders()
    {
        return $this->client->orders;
    }

    /**
     * Invoices APIアクセス
     * @return \Square\Invoices\InvoicesClient
     */
    public function invoices()
    {
        return $this->client->invoices;
    }

    /**
     * Catalog APIアクセス
     * @return \Square\Catalog\CatalogClient
     */
    public function catalog()
    {
        return $this->client->catalog;
    }

    /**
     * Inventory APIアクセス
     * @return \Square\Inventory\InventoryClient
     */
    public function inventory()
    {
        return $this->client->inventory;
    }

    /**
     * 元のSquareClientを取得（必要な場合）
     */
    public function getClient(): SquareClient
    {
        return $this->client;
    }

    /**
     * 接続テスト用のヘルパーメソッド
     */
    public function testConnection(): array
    {
        try {
            /** @var \Square\Types\ListLocationsResponse */
            $response = $this->locations()->list();
            
            // エラーチェック
            $errors = $response->getErrors();
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'message' => 'Square API connection failed',
                    'errors' => $errors
                ];
            }
            
            // 成功時
            $locations = $response->getLocations();
            
            return [
                'success' => true,
                'message' => 'Square API connection successful',
                'locations_count' => count($locations ?? [])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }
}