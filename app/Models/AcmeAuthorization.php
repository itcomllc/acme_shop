<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class AcmeAuthorization extends Model
{
    protected $fillable = [
        'order_id',
        'identifier',
        'status',
        'expires'
    ];

    protected $casts = [
        'identifier' => 'array',
        'expires' => 'datetime'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(AcmeOrder::class, 'order_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(AcmeChallenge::class, 'authorization_id');
    }
}
