<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\TenantRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->editedUser()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => [
                'required',
                Rule::enum(UserRole::class)->only(UserRole::tenantRoles()),
                // Guards against an admin locking themselves (and possibly everyone) out.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->user()?->is($this->editedUser()) && $value !== $this->editedUser()->role()?->value) {
                        $fail('You cannot change your own role.');
                    }
                },
            ],
            'client_id' => ['nullable', 'required_if:role,'.UserRole::ClientUser->value, 'integer', TenantRules::exists('clients')->withoutTrashed()],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'distinct', TenantRules::exists('categories')],
        ];
    }

    public function editedUser(): User
    {
        /** @var User */
        return $this->route('user');
    }

    public function role(): UserRole
    {
        return $this->enum('role', UserRole::class) ?? UserRole::Technician;
    }

    public function clientId(): ?int
    {
        return $this->filled('client_id') ? $this->integer('client_id') : null;
    }

    /**
     * @return list<int>
     */
    public function skillIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('skill_ids', [])));
    }
}
