<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Report;
use App\Models\User;

final class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewReports);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->hasPermission(Permission::ViewReports);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ViewReports);
    }
}
