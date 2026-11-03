<?php

use App\Actions\Tenants\ProvisionTenant;
use App\Actions\Tenants\SyncTenantRoles;
use App\Enums\Permission;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Spatie\Permission\Models\Role;

it('provisions roles, SLA policies and categories on signup', function () {
    $this->post('http://'.config('tenancy.domain').'/signup', [
        'company_name' => 'Acme Facilities',
        'slug' => 'acme',
        'admin_name' => 'Asha',
        'admin_email' => 'asha@acme.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertSessionHasNoErrors();

    $tenant = Tenant::where('slug', 'acme')->sole();

    expect(Role::where('tenant_id', $tenant->id)->pluck('name')->sort()->values()->all())
        ->toBe(['client_user', 'supervisor', 'technician', 'tenant_admin']);

    $policies = SlaPolicy::where('tenant_id', $tenant->id)->get()->keyBy(fn ($p) => $p->priority->value);
    expect($policies)->toHaveCount(4)
        ->and($policies['critical']->response_minutes)->toBe(15)
        ->and($policies['critical']->resolution_minutes)->toBe(240)
        ->and($policies['low']->resolution_minutes)->toBe(4320);

    expect(Category::where('tenant_id', $tenant->id)->pluck('name')->sort()->values()->all())
        ->toBe(collect(Category::DEFAULTS)->sort()->values()->all());

    $admin = User::where('email', 'asha@acme.test')->sole();
    app(TenantContext::class)->run($tenant, function () use ($admin) {
        expect($admin->fresh()->role())->toBe(UserRole::TenantAdmin)
            ->and($admin->fresh()->hasPermission(Permission::ManageUsers))->toBeTrue();
    });
});

it('gives each role exactly its permissions', function (UserRole $role) {
    $user = userWithRole($role);

    app(TenantContext::class)->run($user->tenant()->sole(), function () use ($user, $role) {
        $granted = $user->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all();
        $expected = collect($role->permissions())->map->value->sort()->values()->all();

        expect($granted)->toBe($expected);
    });
})->with(UserRole::tenantRoles());

it('scopes roles to their tenant', function () {
    $admin = userWithRole(UserRole::TenantAdmin);
    $otherTenant = tenantWithRoles();

    // Same user model, other tenant in context: the role does not follow them.
    app(TenantContext::class)->run($otherTenant, function () use ($admin) {
        expect($admin->fresh()->role())->toBeNull()
            ->and($admin->fresh()->hasPermission(Permission::ManageUsers))->toBeFalse();
    });
});

it('is idempotent and adds permissions introduced later', function () {
    $tenant = tenantWithRoles();

    app(SyncTenantRoles::class)->handle($tenant);

    expect(Role::where('tenant_id', $tenant->id)->count())->toBe(4);
});

it('provisions every tenant from the console', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->artisan('tenancy:provision')
        ->expectsOutputToContain($tenants[0]->slug)
        ->expectsOutputToContain($tenants[1]->slug)
        ->assertSuccessful();

    expect(Role::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(8)
        ->and(SlaPolicy::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(8)
        ->and(Category::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(12);
});

it('can provision a single tenant from the console', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->artisan('tenancy:provision', ['tenant' => $tenants[0]->slug])->assertSuccessful();

    expect(Role::where('tenant_id', $tenants[0]->id)->count())->toBe(4)
        ->and(Role::where('tenant_id', $tenants[1]->id)->count())->toBe(0);
});

it('never overwrites what a tenant has customised', function () {
    $tenant = Tenant::factory()->create();
    app(ProvisionTenant::class)->handle($tenant);

    $policy = SlaPolicy::where('tenant_id', $tenant->id)->where('priority', 'critical')->sole();
    $policy->update(['response_minutes' => 5, 'resolution_minutes' => 60]);
    Category::where('tenant_id', $tenant->id)->where('name', 'HVAC')->update(['name' => 'Air conditioning']);
    SlaPolicy::where('tenant_id', $tenant->id)->where('priority', 'low')->delete();

    app(ProvisionTenant::class)->handle($tenant);

    expect($policy->fresh()->response_minutes)->toBe(5)
        ->and(SlaPolicy::where('tenant_id', $tenant->id)->count())->toBe(4)
        ->and(Category::where('tenant_id', $tenant->id)->pluck('name'))->toContain('Air conditioning')->not->toContain('HVAC');
});

it('uses the default SLA table', function (Priority $priority, int $response, int $resolution) {
    expect($priority->defaultSla())->toBe(['response' => $response, 'resolution' => $resolution]);
})->with([
    [Priority::Critical, 15, 240],
    [Priority::High, 60, 480],
    [Priority::Medium, 240, 1440],
    [Priority::Low, 1440, 4320],
]);
