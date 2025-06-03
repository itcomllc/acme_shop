<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};

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
        'square_data'
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
        'square_data' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAddDomain(): bool
    {
        return $this->certificates()->count() < $this->max_domains;
    }

    public function isExpired(): bool
    {
        return $this->next_billing_date && $this->next_billing_date->isPast();
    }
}
