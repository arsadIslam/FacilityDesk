<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

/**
 * Makes a tenant's roles match UserRole::permissions(). Idempotent: run on
 * signup and again (via tenancy:sync-roles) whenever permissions change.
 *
 * Roles are per tenant (team-scoped) so a tenant can later customise its own
 * roles without affecting anyone else; permissions are global.
 */
final class SyncTenantRoles
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            foreach (Permission::cases() as $permission) {
                PermissionModel::findOrCreate($permission->value, 'web');
            }

            $this->context->run($tenant, function (Tenant $tenant): void {
                foreach (UserRole::tenantRoles() as $userRole) {
                    $role = Role::query()->firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'name' => $userRole->value,
                        'guard_name' => 'web',
                    ]);

                    $role->syncPermissions(array_map(
                        fn (Permission $permission) => $permission->value,
                        $userRole->permissions(),
                    ));
                }
            });
        });
    }
}
