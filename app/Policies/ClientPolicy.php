<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;

final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewClients);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageClients);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasPermission(Permission::ManageClients);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasPermission(Permission::ManageClients);
    }
}
