<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewUsers);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::ManageUsers) && ! $user->is($model);
    }
}
