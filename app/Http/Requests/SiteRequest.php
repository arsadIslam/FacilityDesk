<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Site;
use App\Rules\UserHasRole;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

final class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site
            ? $this->user()?->can('update', $site) ?? false
            : $this->user()?->can('create', Site::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $site = $this->route('site');

        return [
            'client_id' => ['required', 'integer', TenantRules::exists('clients')->withoutTrashed()],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', TenantRules::unique('sites', 'code', $site instanceof Site ? $site : null)],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone:all'],
            'supervisor_id' => ['nullable', 'integer', new UserHasRole(UserRole::Supervisor, UserRole::TenantAdmin)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => 'The site code may only contain letters, numbers and hyphens.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
