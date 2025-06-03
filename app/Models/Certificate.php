<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Certificate extends Model
{
    protected $fillable = [
        'subscription_id',
        'domain',
        'type',
        'provider',
        'provider_certificate_id',
        'provider_data',
        'status',
        'acme_order_id',
        'gogetssl_order_id', // Backward compatibility
        'private_key',
        'certificate_data',
        'expires_at',
        'issued_at',
        'replaced_by',
        'replaced_at',
        'revoked_at',
        'revocation_reason',
    ];

    protected $casts = [
        'certificate_data' => 'array',
        'provider_data' => 'array',
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'replaced_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Certificate statuses
     */
    public const STATUS_PENDING = 'pending_validation';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Certificate providers
     */
    public const PROVIDER_GOGETSSL = 'gogetssl';
    public const PROVIDER_GOOGLE_CM = 'google_certificate_manager';
    public const PROVIDER_LETS_ENCRYPT = 'lets_encrypt';

    /**
     * Get the subscription that owns the certificate
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the ACME order associated with the certificate
     */
    public function acmeOrder(): BelongsTo
    {
        return $this->belongsTo(AcmeOrder::class, 'acme_order_id');
    }

    /**
     * Get the validation records for the certificate
     */
    public function validationRecords(): HasMany
    {
        return $this->hasMany(CertificateValidation::class);
    }

    /**
     * Get the renewal records for the certificate
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(CertificateRenewal::class);
    }

    /**
     * Get the certificate that replaced this one
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'replaced_by');
    }

    /**
     * Get certificates that were replaced by this one
     */
    public function replacedCertificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'replaced_by');
    }

    /**
     * Check if certificate is expiring soon
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at && $this->expires_at->lessThan(now()->addDays($days));
    }

    /**
     * Check if certificate is valid and active
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_ISSUED && 
               $this->expires_at && 
               $this->expires_at->isFuture() &&
               !$this->isRevoked();
    }

    /**
     * Check if certificate is revoked
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED || !is_null($this->revoked_at);
    }

    /**
     * Check if certificate has been replaced
     */
    public function isReplaced(): bool
    {
        return $this->status === self::STATUS_REPLACED || !is_null($this->replaced_by);
    }

    /**
     * Get the certificate age in days
     */
    public function getAgeInDays(): int
    {
        if (!$this->issued_at) {
            return 0;
        }

        return $this->issued_at->diffInDays(now());
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Get provider display name
     */
    public function getProviderDisplayName(): string
    {
        switch ($this->provider) {
            case self::PROVIDER_GOGETSSL:
                return 'GoGetSSL';
            case self::PROVIDER_GOOGLE_CM:
                return 'Google Certificate Manager';
            case self::PROVIDER_LETS_ENCRYPT:
                return "Let's Encrypt";
            default:
                return ucfirst($this->provider);
        }
    }

    /**
     * Get certificate type display name
     */
    public function getTypeDisplayName(): string
    {
        switch ($this->type) {
            case 'DV':
                return 'Domain Validated';
            case 'OV':
                return 'Organization Validated';
            case 'EV':
                return 'Extended Validation';
            default:
                return $this->type;
        }
    }

    /**
     * Get status display name with color coding
     */
    public function getStatusDisplayName(): string
    {
        switch ($this->status) {
            case self::STATUS_PENDING:
                return 'Pending Validation';
            case self::STATUS_PROCESSING:
                return 'Processing';
            case self::STATUS_ISSUED:
                return 'Issued';
            case self::STATUS_FAILED:
                return 'Failed';
            case self::STATUS_REVOKED:
                return 'Revoked';
            case self::STATUS_EXPIRED:
                return 'Expired';
            case self::STATUS_REPLACED:
                return 'Replaced';
            case self::STATUS_SUSPENDED:
                return 'Suspended';
            default:
                return ucfirst($this->status);
        }
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        switch ($this->status) {
            case self::STATUS_ISSUED:
                return 'green';
            case self::STATUS_PENDING:
            case self::STATUS_PROCESSING:
                return 'yellow';
            case self::STATUS_FAILED:
            case self::STATUS_REVOKED:
            case self::STATUS_EXPIRED:
                return 'red';
            case self::STATUS_REPLACED:
                return 'gray';
            case self::STATUS_SUSPENDED:
                return 'orange';
            default:
                return 'gray';
        }
    }

    /**
     * Check if certificate supports auto-renewal
     */
    public function supportsAutoRenewal(): bool
    {
        return in_array($this->provider, [
            self::PROVIDER_GOOGLE_CM,
            self::PROVIDER_LETS_ENCRYPT
        ]);
    }

    /**
     * Get provider capabilities
     */
    public function getProviderCapabilities(): array
    {
        $config = config('ssl-enhanced.providers.' . $this->provider, []);
        
        return [
            'auto_renewal' => $config['auto_renewal'] ?? false,
            'wildcard_support' => $config['features']['wildcard_support'] ?? false,
            'san_support' => $config['features']['san_support'] ?? false,
            'download_support' => $config['features']['download_support'] ?? false,
            'revocation' => $config['features']['revocation'] ?? false,
            'validation_methods' => $config['validation_methods'] ?? [],
            'max_validity_days' => $config['max_validity_days'] ?? 90,
        ];
    }

    /**
     * Log certificate activity
     */
    public function logActivity(string $action, array $data = []): void
    {
        Log::info("Certificate {$action}", array_merge([
            'certificate_id' => $this->id,
            'domain' => $this->domain,
            'provider' => $this->provider,
            'status' => $this->status,
        ], $data));
    }

    /**
     * Scope for active certificates
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ISSUED)
                    ->where('expires_at', '>', now())
                    ->whereNull('revoked_at');
    }

    /**
     * Scope for expiring certificates
     */
    public function scopeExpiring($query, int $days = 30)
    {
        return $query->where('status', self::STATUS_ISSUED)
                    ->where('expires_at', '<=', now()->addDays($days))
                    ->where('expires_at', '>', now())
                    ->whereNull('revoked_at');
    }

    /**
     * Scope for certificates by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for renewable certificates
     */
    public function scopeRenewable($query)
    {
        return $query->where('status', self::STATUS_ISSUED)
                    ->whereNull('revoked_at')
                    ->whereNull('replaced_by');
    }

    /**
     * Get formatted expires at date
     */
    public function getFormattedExpiresAtAttribute(): string
    {
        if (!$this->expires_at) {
            return 'N/A';
        }

        return $this->expires_at->format('M j, Y g:i A');
    }

    /**
     * Get human readable time until expiration
     */
    public function getTimeUntilExpirationAttribute(): string
    {
        if (!$this->expires_at) {
            return 'N/A';
        }

        if ($this->expires_at->isPast()) {
            return 'Expired ' . $this->expires_at->diffForHumans();
        }

        return 'Expires ' . $this->expires_at->diffForHumans();
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($certificate) {
            $certificate->logActivity('creating', [
                'provider' => $certificate->provider,
                'type' => $certificate->type
            ]);
        });

        static::updated(function ($certificate) {
            if ($certificate->isDirty('status')) {
                $certificate->logActivity('status_changed', [
                    'old_status' => $certificate->getOriginal('status'),
                    'new_status' => $certificate->status
                ]);
            }
        });
    }
}