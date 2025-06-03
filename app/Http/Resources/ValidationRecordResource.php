<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Validation Record Resource
 */
class ValidationRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'token' => $this->token,
            'key_authorization' => $this->key_authorization,
            'status' => $this->status,
            'validated_at' => $this->validated_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}