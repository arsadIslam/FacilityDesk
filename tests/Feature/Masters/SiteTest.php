<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Site;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['timezone' => 'Asia/Dubai']);
    actingAsRole(UserRole::TenantAdmin, $this->tenant);
    $this->client = Client::factory()->forTenant($this->tenant)->create();
});

function siteData(array $overrides = []): array
{
    return array_merge([
        'client_id' => test()->client->id,
        'name' => 'HQ Tower',
        'code' => 'hq-01',
        'city' => 'Pune',
        'timezone' => 'Asia/Kolkata',
        'is_active' => '1',
    ], $overrides);
}

it('defaults a new site to the tenant timezone', function () {
    $this->get($this->tenant->url('sites/create'))
        ->assertOk()
        ->assertSee('<option value="Asia/Dubai" selected', false);
});

it('creates a site with an uppercase code', function () {
    $this->post($this->tenant->url('sites'), siteData())->assertSessionHasNoErrors();

    expect(Site::sole())->code->toBe('HQ-01')->tenant_id->toBe($this->tenant->id);
});

it('assigns a supervisor', function () {
    $supervisor = userWithRole(UserRole::Supervisor, $this->tenant);

    $this->post($this->tenant->url('sites'), siteData(['supervisor_id' => $supervisor->id]))->assertSessionHasNoErrors();

    expect(Site::sole()->supervisor_id)->toBe($supervisor->id);
});

it('only accepts supervisors or admins of this tenant as site supervisor', function () {
    $technician = userWithRole(UserRole::Technician, $this->tenant);
    $foreignSupervisor = userWithRole(UserRole::Supervisor);

    $this->post($this->tenant->url('sites'), siteData(['supervisor_id' => $technician->id]))->assertSessionHasErrors('supervisor_id');
    $this->post($this->tenant->url('sites'), siteData(['supervisor_id' => $foreignSupervisor->id]))->assertSessionHasErrors('supervisor_id');
});

it('rejects a client from another tenant', function () {
    $this->post($this->tenant->url('sites'), siteData(['client_id' => Client::factory()->create()->id]))
        ->assertSessionHasErrors('client_id');
});

it('validates site input', function (array $overrides, string $field) {
    Site::factory()->forClient($this->client)->create(['code' => 'TAKEN']);

    $this->post($this->tenant->url('sites'), siteData($overrides))->assertSessionHasErrors($field);
})->with([
    'duplicate code' => [['code' => 'taken'], 'code'],
    'bad code' => [['code' => 'has space'], 'code'],
    'bad timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'missing client' => [['client_id' => null], 'client_id'],
]);

it('lists only this tenant\'s sites', function () {
    Site::factory()->forClient($this->client)->create(['name' => 'Own Site']);
    Site::factory()->create(['name' => 'Foreign Site']);

    $this->get($this->tenant->url('sites'))
        ->assertOk()
        ->assertSee('Own Site')
        ->assertDontSee('Foreign Site');
});

it('shows a site', function () {
    $site = Site::factory()->forClient($this->client)->create(['name' => 'Tower B']);

    $this->get($this->tenant->url("sites/{$site->id}"))->assertOk()->assertSee('Tower B');
});

it('updates and soft deletes a site', function () {
    $site = Site::factory()->forClient($this->client)->create();

    $this->put($this->tenant->url("sites/{$site->id}"), siteData(['name' => 'Renamed', 'code' => $site->code]))->assertSessionHasNoErrors();
    expect($site->fresh()->name)->toBe('Renamed');

    $this->delete($this->tenant->url("sites/{$site->id}"))->assertRedirect($this->tenant->url('sites'));
    $this->assertSoftDeleted($site);
});

it('keeps technicians out of sites', function () {
    actingAsRole(UserRole::Technician, $this->tenant);

    $this->get($this->tenant->url('sites'))->assertForbidden();
});
