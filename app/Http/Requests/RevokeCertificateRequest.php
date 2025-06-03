<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Revoke Certificate Request
 */
class RevokeCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|in:unspecified,keyCompromise,cACompromise,affiliationChanged,superseded,cessationOfOperation,certificateHold,removeFromCRL',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A revocation reason is required.',
            'reason.in' => 'The revocation reason must be a valid RFC 5280 reason code.',
        ];
    }
}