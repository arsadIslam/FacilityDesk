<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\User;

final class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewAssets);
    }

    /**
     * Staff see every asset in the tenant; client users only their client's,
     * which is what lets them scan a QR code on site and raise a work order.
     */
    public function view(User $user, Asset $asset): bool
    {
        if ($user->hasPermission(Permission::ViewAssets)) {
            return true;
        }

        return $user->hasUserRole(UserRole::ClientUser)
            && $user->client_id !== null
            && $asset->loadMissing('site')->site->client_id === $user->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageAssets);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->hasPermission(Permission::ManageAssets);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasPermission(Permission::ManageAssets);
    }
}
