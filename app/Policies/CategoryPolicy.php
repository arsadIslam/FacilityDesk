<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;

final class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewCategories);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageCategories);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission(Permission::ManageCategories);
    }
}
