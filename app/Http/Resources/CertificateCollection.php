<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Certificate Collection
 */
class CertificateCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_count' => $this->collection->count(),
                'status_summary' => $this->getStatusSummary(),
                'provider_summary' => $this->getProviderSummary(),
                'expiration_summary' => $this->getExpirationSummary(),
            ]
        ];
    }

    private function getStatusSummary(): array
    {
        $summary = [];
        foreach ($this->collection as $certificate) {
            $status = $certificate->status;
            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }
        return $summary;
    }

    private function getProviderSummary(): array
    {
        $summary = [];
        foreach ($this->collection as $certificate) {
            $provider = $certificate->provider;
            $summary[$provider] = ($summary[$provider] ?? 0) + 1;
        }
        return $summary;
    }

    private function getExpirationSummary(): array
    {
        $expiring7Days = 0;
        $expiring30Days = 0;
        $expired = 0;

        foreach ($this->collection as $certificate) {
            if ($certificate->expires_at) {
                $daysLeft = $certificate->getDaysUntilExpiration();
                if ($daysLeft < 0) {
                    $expired++;
                } elseif ($daysLeft <= 7) {
                    $expiring7Days++;
                } elseif ($daysLeft <= 30) {
                    $expiring30Days++;
                }
            }
        }

        return [
            'expiring_7_days' => $expiring7Days,
            'expiring_30_days' => $expiring30Days,
            'expired' => $expired,
        ];
    }
}
