<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EabCredential extends Model
{
    protected $fillable = [
        'subscription_id',
        'mac_id',
        'mac_key',
        'is_active',
        'last_used_at',
        'usage_count'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer'
    ];

    protected $hidden = [
        'mac_key' // セキュリティのため隠蔽
    ];

    /**
     * Get the subscription that owns this EAB credential
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get ACME accounts using this EAB credential
     */
    public function acmeAccounts(): HasMany
    {
        return $this->hasMany(AcmeAccount::class, 'eab_credential_id');
    }

    /**
     * Record EAB usage
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get subscription EAB credentials relationship
     */
    public function eabCredentials(): HasMany
    {
        return $this->subscription->hasMany(EabCredential::class);
    }

    /**
     * Check if credential is valid for use
     */
    public function isValid(): bool
    {
        return $this->is_active && 
               $this->subscription->isActive() &&
               !$this->isExpired();
    }

    /**
     * Check if credential has expired
     */
    public function isExpired(): bool
    {
        // EAB credentials expire when subscription expires
        return $this->subscription->isExpired();
    }

    /**
     * Generate new MAC credentials
     */
    public static function generateForSubscription(Subscription $subscription): self
    {
        return self::create([
            'subscription_id' => $subscription->id,
            'mac_id' => 'eab_' . bin2hex(random_bytes(16)),
            'mac_key' => base64_encode(random_bytes(32)),
            'is_active' => true,
            'usage_count' => 0
        ]);
    }

    /**
     * Revoke EAB credential
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }
}
