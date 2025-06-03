<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Certificate Renewal Resource
 */
class CertificateRenewalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}