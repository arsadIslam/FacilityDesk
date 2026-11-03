<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class TenantSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:150'],
            // DNS label: lowercase letters, digits and inner hyphens.
            'slug' => [
                'required', 'string', 'min:3', 'max:60',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(config('tenancy.reserved_slugs')),
                Rule::unique('tenants', 'slug'),
            ],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The workspace URL may only contain lowercase letters, numbers and hyphens.',
            'slug.not_in' => 'This workspace URL is reserved. Please choose another.',
            'slug.unique' => 'This workspace URL is already taken.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'admin_email' => strtolower(trim((string) $this->input('admin_email'))),
        ]);
    }
}
