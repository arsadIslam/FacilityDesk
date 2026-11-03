<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    /** Platform operator; identified by tenant_id = NULL, never stored as a tenant role. */
    case SuperAdmin = 'super_admin';

    case TenantAdmin = 'tenant_admin';
    case Supervisor = 'supervisor';
    case Technician = 'technician';
    case ClientUser = 'client_user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::TenantAdmin => 'Tenant Admin',
            self::Supervisor => 'Supervisor',
            self::Technician => 'Technician',
            self::ClientUser => 'Client User',
        };
    }

    /**
     * Roles provisioned inside every tenant.
     *
     * @return list<self>
     */
    public static function tenantRoles(): array
    {
        return [self::TenantAdmin, self::Supervisor, self::Technician, self::ClientUser];
    }

    /**
     * Roles that can be put on a site's coverage list.
     *
     * @return list<self>
     */
    public static function fieldRoles(): array
    {
        return [self::Supervisor, self::Technician];
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [],
            self::TenantAdmin => Permission::cases(),
            self::Supervisor => [
                Permission::ViewUsers,
                Permission::ViewClients,
                Permission::ViewSites,
                Permission::ViewCategories,
                Permission::ViewSlaPolicies,
            ],
            // Work-order permissions for these two arrive in Phase 3.
            self::Technician => [],
            self::ClientUser => [],
        };
    }
}
