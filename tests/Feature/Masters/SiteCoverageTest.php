<?php

use App\Enums\UserRole;
use App\Models\Site;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    actingAsRole(UserRole::TenantAdmin, $this->tenant);
    $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('syncs the technicians and supervisors covering a site', function () {
    $technician = userWithRole(UserRole::Technician, $this->tenant);
    $supervisor = userWithRole(UserRole::Supervisor, $this->tenant);

    $this->put($this->tenant->url("sites/{$this->site->id}/coverage"), ['user_ids' => [$technician->id, $supervisor->id]])
        ->assertSessionHasNoErrors();

    expect($this->site->coverage()->pluck('users.id')->all())->toEqualCanonicalizing([$technician->id, $supervisor->id]);
    $this->assertDatabaseHas('site_user', ['site_id' => $this->site->id, 'user_id' => $technician->id, 'tenant_id' => $this->tenant->id]);

    $this->put($this->tenant->url("sites/{$this->site->id}/coverage"), ['user_ids' => [$supervisor->id]]);

    expect($this->site->coverage()->pluck('users.id')->all())->toBe([$supervisor->id]);
});

it('clears coverage when nobody is selected', function () {
    $technician = userWithRole(UserRole::Technician, $this->tenant);
    $this->site->coverage()->attach($technician, ['tenant_id' => $this->tenant->id]);

    $this->put($this->tenant->url("sites/{$this->site->id}/coverage"), [])->assertSessionHasNoErrors();

    expect($this->site->coverage()->count())->toBe(0);
});

it('rejects users who are not field staff of this tenant', function (Closure $makeUser) {
    $user = $makeUser($this->tenant);

    $this->put($this->tenant->url("sites/{$this->site->id}/coverage"), ['user_ids' => [$user->id]])
        ->assertSessionHasErrors('user_ids');

    expect($this->site->coverage()->count())->toBe(0);
})->with([
    'client user' => fn ($tenant) => userWithRole(UserRole::ClientUser, $tenant),
    'other tenant technician' => fn ($tenant) => userWithRole(UserRole::Technician),
    'inactive technician' => fn ($tenant) => userWithRole(UserRole::Technician, $tenant, ['is_active' => false]),
]);

it('shows coverage candidates on the site page', function () {
    userWithRole(UserRole::Technician, $this->tenant, ['name' => 'Tina Tech']);
    userWithRole(UserRole::ClientUser, $this->tenant, ['name' => 'Carl Client']);

    $this->get($this->tenant->url("sites/{$this->site->id}"))
        ->assertSee('Tina Tech (Technician)')
        ->assertDontSee('Carl Client');
});
