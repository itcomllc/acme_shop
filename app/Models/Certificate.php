<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Certificate extends Model
{
    protected $fillable = [
        'subscription_id',
        'domain',
        'type',
        'status',
        'acme_order_id',
        'gogetssl_order_id',
        'private_key',
        'certificate_data',
        'expires_at',
        'issued_at'
    ];

    protected $casts = [
        'certificate_data' => 'array',
        'expires_at' => 'datetime',
        'issued_at' => 'datetime'
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function acmeOrder(): BelongsTo
    {
        return $this->belongsTo(AcmeOrder::class, 'acme_order_id');
    }

    public function validationRecords(): HasMany
    {
        return $this->hasMany(CertificateValidation::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(CertificateRenewal::class);
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at && $this->expires_at->lessThan(now()->addDays($days));
    }

    public function isValid(): bool
    {
        return $this->status === 'issued' && 
               $this->expires_at && 
               $this->expires_at->isFuture();
    }
}
