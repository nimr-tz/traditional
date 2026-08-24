<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Email is deliberately not editable here: it's verified at registration
     * and used as the identity for badges, certificates, and admin lookups,
     * so changing it is out of scope for self-service profile edits.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'salutation' => ['nullable', 'string', Rule::in(config('tmsc.salutations'))],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
