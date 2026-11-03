<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Site;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    actingAsRole(UserRole::TenantAdmin, $this->tenant);
});

it('lists only this tenant\'s clients', function () {
    Client::factory()->forTenant($this->tenant)->create(['name' => 'Own Client']);
    Client::factory()->create(['name' => 'Foreign Client']);

    $this->get($this->tenant->url('clients'))
        ->assertOk()
        ->assertSee('Own Client')
        ->assertDontSee('Foreign Client');
});

it('creates a client', function () {
    $this->post($this->tenant->url('clients'), [
        'name' => 'Globex',
        'contact_person' => 'Hank',
        'gstin' => '27aapfu0939f1zv',
        'is_active' => '1',
    ])->assertRedirect($this->tenant->url('clients'));

    $client = Client::sole();
    expect($client->tenant_id)->toBe($this->tenant->id)
        ->and($client->gstin)->toBe('27AAPFU0939F1ZV')
        ->and($client->is_active)->toBeTrue();
});

it('validates client input', function (array $data, string $field) {
    Client::factory()->forTenant($this->tenant)->create(['name' => 'Taken']);

    $this->post($this->tenant->url('clients'), $data)->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'duplicate name' => [['name' => 'Taken'], 'name'],
    'bad gstin' => [['name' => 'X', 'gstin' => '12345'], 'gstin'],
    'bad email' => [['name' => 'X', 'email' => 'nope'], 'email'],
]);

it('allows a client name that another tenant already uses', function () {
    Client::factory()->create(['name' => 'Globex']);

    $this->post($this->tenant->url('clients'), ['name' => 'Globex'])->assertSessionHasNoErrors();
});

it('updates a client', function () {
    $client = Client::factory()->forTenant($this->tenant)->create();

    $this->put($this->tenant->url("clients/{$client->id}"), ['name' => 'Renamed'])
        ->assertRedirect($this->tenant->url('clients'));

    expect($client->fresh())->name->toBe('Renamed')->is_active->toBeFalse();
});

it('soft deletes a client without sites', function () {
    $client = Client::factory()->forTenant($this->tenant)->create();

    $this->delete($this->tenant->url("clients/{$client->id}"))->assertRedirect();

    $this->assertSoftDeleted($client);
});

it('refuses to delete a client that still has sites', function () {
    $client = Client::factory()->forTenant($this->tenant)->create();
    Site::factory()->forClient($client)->create();

    $this->delete($this->tenant->url("clients/{$client->id}"))->assertSessionHasErrors('client');

    $this->assertNotSoftDeleted($client);
});

it('lets supervisors view but not change clients', function () {
    actingAsRole(UserRole::Supervisor, $this->tenant);
    $client = Client::factory()->forTenant($this->tenant)->create();

    $this->get($this->tenant->url('clients'))->assertOk();
    $this->post($this->tenant->url('clients'), ['name' => 'X'])->assertForbidden();
    $this->delete($this->tenant->url("clients/{$client->id}"))->assertForbidden();
});
