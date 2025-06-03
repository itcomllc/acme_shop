<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateValidation extends Model
{
    protected $fillable = [
        'certificate_id',
        'type',
        'token',
        'key_authorization',
        'status',
        'validated_at'
    ];

    protected $casts = [
        'validated_at' => 'datetime'
    ];

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
