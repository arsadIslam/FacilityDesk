<?php

use App\Actions\Tenants\SyncTenantRoles;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
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

/**
 * Put $tenant in context the way IdentifyTenant does for a request. Needed
 * for code that runs outside HTTP, such as Livewire::test() and actions.
 */
function enterTenant(Tenant $tenant): Tenant
{
    app(TenantContext::class)->set($tenant);
    URL::defaults(['tenant' => $tenant->slug]);

    // Relative test requests (Livewire::test() posts to "/livewire/update")
    // go to the tenant's subdomain, as they would from a browser on it.
    URL::forceRootUrl($tenant->url());

    return $tenant;
}

/**
 * A tenant with one user per role, a client with a site, a location and a
 * category: everything a work order needs. The tenant is left in context.
 */
function workOrderWorld(): stdClass
{
    $w = new stdClass;
    $w->tenant = tenantWithRoles(['timezone' => 'Asia/Kolkata']);
    $w->client = Client::factory()->forTenant($w->tenant)->create();
    $w->site = Site::factory()->forClient($w->client)->create();
    $w->location = Location::factory()->forSite($w->site)->create();
    $w->category = Category::factory()->forTenant($w->tenant)->create(['default_priority' => Priority::High]);

    $w->admin = userWithRole(UserRole::TenantAdmin, $w->tenant);
    $w->supervisor = userWithRole(UserRole::Supervisor, $w->tenant);
    $w->technician = userWithRole(UserRole::Technician, $w->tenant);
    $w->otherTechnician = userWithRole(UserRole::Technician, $w->tenant);
    $w->clientUser = userWithRole(UserRole::ClientUser, $w->tenant);
    $w->clientUser->forceFill(['client_id' => $w->client->id])->save();

    enterTenant($w->tenant);

    return $w;
}
