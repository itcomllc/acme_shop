<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * Subscription Resource
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_type' => $this->plan_type,
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayName(),
            'status_color' => $this->getStatusColor(),
            'max_domains' => $this->max_domains,
            'domains_used' => $this->certificates()->count(),
            'domains_available' => $this->getRemainingDomainSlots(),
            'certificate_type' => $this->certificate_type,
            'billing_period' => $this->billing_period,
            'billing_period_display' => $this->getBillingPeriodDisplay(),
            'price' => $this->price,
            'formatted_price' => $this->getFormattedPrice(),
            'default_provider' => $this->getDefaultProvider(),
            'auto_renewal_enabled' => $this->auto_renewal_enabled,
            'renewal_before_days' => $this->renewal_before_days,
            'next_billing_date' => $this->next_billing_date?->toISOString(),
            'last_payment_date' => $this->last_payment_date?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Statistics
            'statistics' => $this->getStatistics(),
            
            // Plan configuration
            'plan_config' => $this->getPlanConfig(),
        ];
    }
}