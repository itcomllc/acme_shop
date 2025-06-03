<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'subscription_id',
        'square_invoice_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'invoice_data'
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'invoice_data' => 'array'
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount / 100, 2);
    }
}
