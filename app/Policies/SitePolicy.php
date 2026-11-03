<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Site;
use App\Models\User;

final class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSites);
    }

    public function view(User $user, Site $site): bool
    {
        if (! $user->hasPermission(Permission::ViewSites)) {
            return false;
        }

        // Client users only ever see their own client's sites.
        return ! $user->hasUserRole(UserRole::ClientUser) || $user->client_id === $site->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageSites);
    }

    /**
     * Also covers the site's location tree and coverage list.
     */
    public function update(User $user, Site $site): bool
    {
        return $user->hasPermission(Permission::ManageSites);
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->hasPermission(Permission::ManageSites);
    }
}
