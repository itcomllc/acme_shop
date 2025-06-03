<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Certificate Resource
 */
class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'type' => $this->type,
            'type_display' => $this->getTypeDisplayName(),
            'provider' => $this->provider,
            'provider_display' => $this->getProviderDisplayName(),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayName(),
            'status_color' => $this->getStatusColor(),
            'issued_at' => $this->issued_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'is_valid' => $this->isValid(),
            'is_revoked' => $this->isRevoked(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'revocation_reason' => $this->revocation_reason,
            'provider_certificate_id' => $this->provider_certificate_id,
            'supports_auto_renewal' => $this->supportsAutoRenewal(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'validation_records' => ValidationRecordResource::collection($this->whenLoaded('validationRecords')),
            'renewals' => CertificateRenewalResource::collection($this->whenLoaded('renewals')),
            
            // Provider capabilities
            'provider_capabilities' => $this->getProviderCapabilities(),
        ];
    }
}
