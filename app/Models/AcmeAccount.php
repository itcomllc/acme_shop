<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcmeAccount extends Model
{
    protected $fillable = [
        'public_key',
        'public_key_thumbprint',
        'contacts',
        'terms_of_service_agreed',
        'status'
    ];

    protected $casts = [
        'contacts' => 'array',
        'terms_of_service_agreed' => 'boolean'
    ];
}
