<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcmeChallenge extends Model
{
    protected $fillable = [
        'authorization_id',
        'type',
        'status',
        'token',
        'key_authorization',
        'validated'
    ];

    protected $casts = [
        'validated' => 'datetime'
    ];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(AcmeAuthorization::class, 'authorization_id');
    }
}
