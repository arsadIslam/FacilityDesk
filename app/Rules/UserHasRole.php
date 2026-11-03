<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the id (or every id in an array) is an active user of the
 * current tenant holding one of the given roles.
 */
final class UserHasRole implements ValidationRule
{
    /** @var list<UserRole> */
    private array $roles;

    public function __construct(UserRole ...$roles)
    {
        $this->roles = array_values($roles);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = array_values(array_unique(array_map('intval', (array) $value)));

        if ($ids === []) {
            return;
        }

        $matches = User::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->role(array_map(fn (UserRole $role) => $role->value, $this->roles))
            ->count();

        if ($matches !== count($ids)) {
            $fail('The selected :attribute must be active '.$this->describeRoles().'.');
        }
    }

    private function describeRoles(): string
    {
        return implode(' or ', array_map(fn (UserRole $role) => strtolower($role->label()).'s', $this->roles));
    }
}
