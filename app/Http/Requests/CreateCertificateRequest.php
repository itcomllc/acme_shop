<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create Certificate Request
 */
class CreateCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id' => 'required|integer|exists:subscriptions,id',
            'domain' => 'required|string|ssl_domain|max:253',
            'provider' => 'nullable|string|ssl_provider',
        ];
    }

    public function messages(): array
    {
        return [
            'domain.ssl_domain' => 'The domain must be a valid domain name or wildcard domain.',
            'provider.ssl_provider' => 'The provider must be a valid SSL certificate provider.',
        ];
    }
}
