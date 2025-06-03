<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionArchive extends Model
{
    protected $fillable = [
        'original_subscription_id',
        'user_id',
        'subscription_data',
        'certificates_data',
        'archived_at'
    ];

    protected $casts = [
        'subscription_data' => 'array',
        'certificates_data' => 'array',
        'archived_at' => 'datetime'
    ];
}
