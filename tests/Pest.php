<?php

use App\Actions\Tenants\SyncTenantRoles;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A tenant with its roles provisioned, like one created through signup.
 */
function tenantWithRoles(array $attributes = []): Tenant
{
    $tenant = Tenant::factory()->create($attributes);
    app(SyncTenantRoles::class)->handle($tenant);

    return $tenant;
}

/**
 * A verified, active user holding $role inside $tenant (a new tenant by default).
 */
function userWithRole(UserRole $role, ?Tenant $tenant = null, array $attributes = []): User
{
    $tenant ??= Tenant::factory()->create();

    // Idempotent, so tenants made with Tenant::factory() work too.
    app(SyncTenantRoles::class)->handle($tenant);

    $user = User::factory()->forTenant($tenant)->create($attributes);
    app(TenantContext::class)->run($tenant, fn () => $user->assignRole($role->value));

    return $user;
}

/**
 * Log in as a new user with $role and return them.
 */
function actingAsRole(UserRole $role, ?Tenant $tenant = null, array $attributes = []): User
{
    $user = userWithRole($role, $tenant, $attributes);
    test()->actingAs($user);

    return $user;
}
