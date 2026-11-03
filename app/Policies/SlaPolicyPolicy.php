<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SlaPolicy;
use App\Models\User;

final class SlaPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSlaPolicies);
    }

    public function update(User $user, SlaPolicy $slaPolicy): bool
    {
        return $user->hasPermission(Permission::ManageSlaPolicies);
    }
}
