<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Role assignments live in Spatie's pivot table, which the model-level
 * activity log cannot see, so they are written to the "roles" log here.
 */
final class RoleChangeLog
{
    public static function record(User $user, ?UserRole $from, UserRole $to): void
    {
        activity('roles')
            ->performedOn($user)
            ->event('role_changed')
            ->withProperties([
                'old' => ['role' => $from?->value],
                'attributes' => ['role' => $to->value],
            ])
            ->log($from === null
                ? "Given the {$to->label()} role"
                : "Role changed from {$from->label()} to {$to->label()}");
    }
}
