<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Renew Certificate Request
 */
class RenewCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'force' => 'boolean',
            'provider' => 'nullable|string|ssl_provider',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.ssl_provider' => 'The provider must be a valid SSL certificate provider.',
        ];
    }
}
