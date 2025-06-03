<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcmeOrder extends Model
{
    protected $fillable = [
        'identifiers',
        'profile',
        'status',
        'expires',
        'certificate_url'
    ];

    protected $casts = [
        'identifiers' => 'array',
        'expires' => 'datetime'
    ];

    public function authorizations(): HasMany
    {
        return $this->hasMany(AcmeAuthorization::class, 'order_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(\App\Models\Certificate::class, 'acme_order_id');
    }
}
