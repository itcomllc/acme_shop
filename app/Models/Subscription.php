<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};
use Illuminate\Support\Facades\{Log, Cache};
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'square_subscription_id',
        'plan_type',
        'status',
        'max_domains',
        'certificate_type',
        'billing_period',
        'price',
        'domains',
        'next_billing_date',
        'last_payment_date',
        'payment_failed_attempts',
        'last_payment_failure',
        'cancelled_at',
        'cancellation_reason',
        'paused_at',
        'resumed_at',
        'square_data',
        'default_provider',
        'provider_preferences',
        'auto_renewal_enabled',
        'renewal_before_days',
        'certificates_issued',
        'certificates_renewed',
        'certificates_failed',
        'last_certificate_issued_at',
        'last_activity_at',
    ];

    protected $casts = [
        'domains' => 'array',
        'price' => 'integer',
        'next_billing_date' => 'datetime',
        'last_payment_date' => 'datetime',
        'last_payment_failure' => 'datetime',
        'cancelled_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'square_data' => 'array',
        'provider_preferences' => 'array',
        'auto_renewal_enabled' => 'boolean',
        'renewal_before_days' => 'integer',
        'certificates_issued' => 'integer',
        'certificates_renewed' => 'integer',
        'certificates_failed' => 'integer',
        'last_certificate_issued_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Subscription statuses
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PAUSED = 'paused';

    /**
     * Plan types
     */
    public const PLAN_BASIC = 'basic';
    public const PLAN_PROFESSIONAL = 'professional';
    public const PLAN_ENTERPRISE = 'enterprise';

    /**
     * Get the user that owns the subscription
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all certificates for this subscription
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Get all payments for this subscription
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all EAB credentials for this subscription
     */
    public function eabCredentials(): HasMany
    {
        return $this->hasMany(EabCredential::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if subscription can add more domains
     */
    public function canAddDomain(): bool
    {
        return $this->certificates()->count() < $this->max_domains;
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->next_billing_date && $this->next_billing_date->isPast();
    }

    /**
     * Check if subscription is past due
     */
    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * Check if subscription is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if subscription is paused
     */
    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Check if subscription is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get remaining domain slots
     */
    public function getRemainingDomainSlots(): int
    {
        return max(0, $this->max_domains - $this->certificates()->count());
    }

    /**
     * Get active certificates
     */
    public function getActiveCertificates()
    {
        return $this->certificates()
            ->where('status', Certificate::STATUS_ISSUED)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Get expiring certificates
     */
    public function getExpiringCertificates(int $days = 30)
    {
        return $this->certificates()
            ->where('status', Certificate::STATUS_ISSUED)
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    /**
     * Get default provider for this subscription
     */
    public function getDefaultProvider(): string
    {
        if ($this->default_provider) {
            return $this->default_provider;
        }

        // Get provider based on plan type
        $planProviders = config('ssl-enhanced.plan_providers');
        return $planProviders[$this->plan_type]['default'] ?? 'gogetssl';
    }

    /**
     * Get provider preferences
     */
    public function getProviderPreferences(): array
    {
        return $this->provider_preferences ?? [];
    }

    /**
     * Set provider preference
     */
    public function setProviderPreference(string $key, $value): void
    {
        $preferences = $this->getProviderPreferences();
        $preferences[$key] = $value;
        $this->update(['provider_preferences' => $preferences]);
    }

    /**
     * Get plan configuration
     */
    public function getPlanConfig(): array
    {
        $plans = [
            self::PLAN_BASIC => [
                'name' => 'Basic SSL',
                'max_domains' => 1,
                'certificate_type' => 'DV',
                'price' => 999,
                'features' => ['1 SSL Certificate', 'Domain Validation', 'Basic Support']
            ],
            self::PLAN_PROFESSIONAL => [
                'name' => 'Professional SSL',
                'max_domains' => 5,
                'certificate_type' => 'OV',
                'price' => 2999,
                'features' => ['5 SSL Certificates', 'Organization Validation', 'Priority Support']
            ],
            self::PLAN_ENTERPRISE => [
                'name' => 'Enterprise SSL',
                'max_domains' => 100,
                'certificate_type' => 'EV',
                'price' => 9999,
                'features' => ['100 SSL Certificates', 'Extended Validation', 'Dedicated Support']
            ]
        ];

        return $plans[$this->plan_type] ?? $plans[self::PLAN_BASIC];
    }

    /**
     * Get subscription statistics
     */
    public function getStatistics(): array
    {
        $certificates = $this->certificates();

        return [
            'total_certificates' => $certificates->count(),
            'active_certificates' => $certificates->where('status', Certificate::STATUS_ISSUED)->count(),
            'pending_certificates' => $certificates->where('status', Certificate::STATUS_PENDING)->count(),
            'failed_certificates' => $certificates->where('status', Certificate::STATUS_FAILED)->count(),
            'expiring_soon' => $this->getExpiringCertificates()->count(),
            'domains_used' => $certificates->count(),
            'domains_available' => $this->getRemainingDomainSlots(),
            'success_rate' => $this->getSuccessRate(),
            'last_activity' => $this->last_activity_at?->diffForHumans(),
        ];
    }

    /**
     * Get certificate success rate
     */
    public function getSuccessRate(): float
    {
        $total = $this->certificates_issued + $this->certificates_failed;

        if ($total === 0) {
            return 0.0;
        }

        return round(($this->certificates_issued / $total) * 100, 2);
    }

    /**
     * Update activity timestamp
     */
    public function updateActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Record certificate issuance
     */
    public function recordCertificateIssued(): void
    {
        $this->increment('certificates_issued');
        $this->update([
            'last_certificate_issued_at' => now(),
            'last_activity_at' => now()
        ]);
    }

    /**
     * Record certificate renewal
     */
    public function recordCertificateRenewed(): void
    {
        $this->increment('certificates_renewed');
        $this->updateActivity();
    }

    /**
     * Record certificate failure
     */
    public function recordCertificateFailure(): void
    {
        $this->increment('certificates_failed');
        $this->updateActivity();
    }

    /**
     * Get next billing amount
     */
    public function getNextBillingAmount(): int
    {
        return $this->price;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price / 100, 2);
    }

    /**
     * Get billing period display
     */
    public function getBillingPeriodDisplay(): string
    {
        return match ($this->billing_period) {
            'MONTHLY' => 'Monthly',
            'QUARTERLY' => 'Quarterly',
            'ANNUALLY' => 'Annually',
            default => ucfirst(strtolower($this->billing_period))
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayName(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAST_DUE => 'Past Due',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_PAUSED => 'Paused',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_PAST_DUE => 'yellow',
            self::STATUS_PAUSED => 'blue',
            self::STATUS_SUSPENDED, self::STATUS_CANCELLED => 'red',
            default => 'gray'
        };
    }

    /**
     * Cache subscription data
     */
    public function cacheData(): void
    {
        $cacheKey = "subscription:{$this->id}";
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_type' => $this->plan_type,
            'status' => $this->status,
            'max_domains' => $this->max_domains,
            'default_provider' => $this->getDefaultProvider(),
            'statistics' => $this->getStatistics(),
        ];

        Cache::put($cacheKey, $data, now()->addHours(1));
    }

    /**
     * Get cached subscription data
     */
    public static function getCached(int $subscriptionId): ?array
    {
        return Cache::get("subscription:{$subscriptionId}");
    }

    /**
     * Log subscription activity
     */
    public function logActivity(string $action, array $data = []): void
    {
        Log::info("Subscription {$action}", array_merge([
            'subscription_id' => $this->id,
            'user_id' => $this->user_id,
            'plan_type' => $this->plan_type,
            'status' => $this->status,
        ], $data));
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for subscriptions by plan
     */
    public function scopeByPlan($query, string $planType)
    {
        return $query->where('plan_type', $planType);
    }

    /**
     * Scope for subscriptions needing renewal
     */
    public function scopeNeedingRenewal($query, int $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('next_billing_date', '<=', now()->addDays($days));
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            $subscription->last_activity_at = now();
        });

        static::updated(function ($subscription) {
            if ($subscription->isDirty('status')) {
                $subscription->logActivity('status_changed', [
                    'old_status' => $subscription->getOriginal('status'),
                    'new_status' => $subscription->status
                ]);
            }

            // Update cache when subscription changes
            $subscription->cacheData();
        });
    }
}
