<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Creates a provisioned tenant and its first Tenant Admin in one
 * transaction, so a half-provisioned tenant can never exist.
 */
final class RegisterTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ProvisionTenant $provisionTenant,
    ) {}

    public function handle(
        string $companyName,
        string $slug,
        string $adminName,
        string $adminEmail,
        string $password,
    ): Tenant {
        [$tenant, $admin] = DB::transaction(function () use ($companyName, $slug, $adminName, $adminEmail, $password): array {
            $tenant = new Tenant([
                'name' => $companyName,
                'slug' => $slug,
                'email' => $adminEmail,
            ]);
            $tenant->trial_ends_at = now()->addDays((int) config('tenancy.trial_days'));
            $tenant->save();

            $this->provisionTenant->handle($tenant);

            // Run as the new tenant so the admin and their role are stamped with its id.
            $admin = $this->context->run($tenant, function (Tenant $tenant) use ($adminName, $adminEmail, $password): User {
                $admin = $tenant->users()->create([
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => $password,
                ]);
                $admin->assignRole(UserRole::TenantAdmin->value);

                return $admin;
            });

            return [$tenant, $admin];
        });

        // After commit: the verification email must never point at a rolled-back tenant.
        event(new Registered($admin));

        return $tenant;
    }
}
