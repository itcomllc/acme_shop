<?php

namespace App\Contracts;

/**
 * Certificate Provider Interface
 * 複数のSSL証明書プロバイダー（GoGetSSL、Google Certificate Manager等）を
 * 統一したインターフェースで扱うための契約
 */
interface CertificateProviderInterface
{
    /**
     * Create new SSL certificate
     * 
     * @param array $domains ドメインリスト
     * @param array $options プロバイダー固有のオプション
     * @return array 証明書作成結果
     * @throws \Exception
     */
    public function createCertificate(array $domains, array $options = []): array;

    /**
     * Get certificate status
     * 
     * @param string $certificateId 証明書ID
     * @return array 証明書状態情報
     * @throws \Exception
     */
    public function getCertificateStatus(string $certificateId): array;

    /**
     * Get validation instructions for certificate
     * 
     * @param string $certificateId 証明書ID
     * @return array DNS/HTTP検証の手順
     * @throws \Exception
     */
    public function getValidationInstructions(string $certificateId): array;

    /**
     * Download certificate files
     * 
     * @param string $certificateId 証明書ID
     * @return array 証明書ファイルデータ
     * @throws \Exception
     */
    public function downloadCertificate(string $certificateId): array;

    /**
     * Revoke certificate
     * 
     * @param string $certificateId 証明書ID
     * @param string $reason 失効理由
     * @return array 失効結果
     * @throws \Exception
     */
    public function revokeCertificate(string $certificateId, string $reason = 'unspecified'): array;

    /**
     * Test provider connection
     * 
     * @return array 接続テスト結果
     */
    public function testConnection(): array;

    /**
     * Get provider name
     * 
     * @return string プロバイダー名
     */
    public function getProviderName(): string;

    /**
     * Get supported certificate types
     * 
     * @return array サポートする証明書タイプ
     */
    public function getSupportedCertificateTypes(): array;

    /**
     * Validate domains for this provider
     * 
     * @param array $domains ドメインリスト
     * @return array バリデーション結果
     */
    public function validateDomains(array $domains): array;
}
