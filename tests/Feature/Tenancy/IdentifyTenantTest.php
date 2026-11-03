<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Route;

it('resolves the tenant from the subdomain', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Facilities']);

    $this->get($tenant->url('login'))
        ->assertOk()
        ->assertSee('Sign in to Acme Facilities');

    expect(app(TenantContext::class)->id())->toBe($tenant->id);
});

it('shows each tenant only its own workspace', function () {
    $acme = Tenant::factory()->create(['name' => 'Acme Facilities']);
    $globex = Tenant::factory()->create(['name' => 'Globex Services']);

    $this->get($globex->url('login'))
        ->assertOk()
        ->assertSee('Globex Services')
        ->assertDontSee('Acme Facilities');
});

it('returns 404 for an unknown subdomain', function () {
    $this->get('http://nobody.'.config('tenancy.domain').'/login')->assertNotFound();

    expect(app(TenantContext::class)->check())->toBeFalse();
});

it('blocks suspended tenants', function () {
    $tenant = Tenant::factory()->suspended()->create();

    $this->get($tenant->url('login'))
        ->assertForbidden()
        ->assertSee('suspended');
});

it('does not serve tenant pages on the central domain', function () {
    $this->get('http://'.config('tenancy.domain').'/')
        ->assertOk()
        ->assertSee('Create your workspace');
});

it('sends guests on a tenant subdomain to that tenant\'s login page', function () {
    $tenant = Tenant::factory()->create();

    $this->get($tenant->url())->assertRedirect($tenant->url('login'));
});

it('identifies the tenant before route-model binding runs', function () {
    $tenantA = Tenant::factory()->create();
    $userB = User::factory()->create();

    Route::domain('{tenant}.'.config('tenancy.domain'))
        ->middleware(['web', 'tenant'])
        ->get('/_test/users/{user}', fn (User $user) => $user->name);

    $this->get($tenantA->url('_test/users/'.$userB->id))->assertNotFound();
});

it('generates tenant URLs without passing the subdomain explicitly', function () {
    $tenant = Tenant::factory()->create();

    Route::domain('{tenant}.'.config('tenancy.domain'))
        ->middleware(['web', 'tenant'])
        ->get('/_test/url', fn () => route('dashboard'));

    $this->get($tenant->url('_test/url'))
        ->assertOk()
        ->assertContent($tenant->url());
});

it('resolves the tenant from the authenticated user when there is no subdomain', function () {
    $user = User::factory()->create();

    // Stand-in for the API group in Phase 6: auth first, then tenant.
    Route::middleware(['web', 'auth', 'tenant', 'tenant.active'])
        ->get('/_test/whoami', fn (TenantContext $context) => ['tenant_id' => $context->id()]);

    $this->actingAs($user)
        ->getJson('http://'.config('tenancy.domain').'/_test/whoami')
        ->assertOk()
        ->assertExactJson(['tenant_id' => $user->tenant_id]);
});

it('returns 404 when neither a subdomain nor a tenant user is present', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    Route::middleware(['web', 'auth', 'tenant'])->get('/_test/whoami', fn () => 'unreachable');

    $this->actingAs($superAdmin)
        ->get('http://'.config('tenancy.domain').'/_test/whoami')
        ->assertNotFound();
});
