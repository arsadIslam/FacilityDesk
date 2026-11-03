<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Client;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

final class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            ? $this->user()?->can('update', $client) ?? false
            : $this->user()?->can('create', Client::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:150', TenantRules::unique('clients', 'name', $client instanceof Client ? $client : null)],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            // Indian GST identification number, e.g. 27AAPFU0939F1ZV.
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['gstin.regex' => 'The GSTIN format is invalid.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gstin' => $this->filled('gstin') ? strtoupper(trim((string) $this->input('gstin'))) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
