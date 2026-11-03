<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:150', TenantRules::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(UserRole::class)->only(UserRole::tenantRoles())],
            'client_id' => ['nullable', 'required_if:role,'.UserRole::ClientUser->value, 'integer', TenantRules::exists('clients')->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function role(): UserRole
    {
        return $this->enum('role', UserRole::class) ?? UserRole::Technician;
    }

    public function clientId(): ?int
    {
        return $this->filled('client_id') ? $this->integer('client_id') : null;
    }
}
