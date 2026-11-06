<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Site;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->actingAs($this->w->admin);
});

function assetData(stdClass $w, array $overrides = []): array
{
    return array_merge([
        'site_id' => $w->site->id,
        'location_id' => $w->location->id,
        'category_id' => $w->category->id,
        'name' => 'Rooftop AC 2',
        'code' => 'hvac-012',
        'status' => 'working',
        'installed_at' => '2025-01-10',
        'warranty_until' => '2027-01-10',
    ], $overrides);
}

it('creates an asset with an uppercase code and a generated QR token', function () {
    $this->post($this->w->tenant->url('assets'), assetData($this->w, ['qr_token' => 'attacker-chosen']))
        ->assertSessionHasNoErrors();

    $asset = Asset::sole();
    expect($asset)
        ->code->toBe('HVAC-012')
        ->tenant_id->toBe($this->w->tenant->id)
        ->status->toBe(AssetStatus::Working)
        ->and($asset->qr_token)->toHaveLength(26)->not->toBe('attacker-chosen');
});

it('validates asset input', function (array $overrides, string $field) {
    Asset::factory()->at($this->w->location)->create(['code' => 'TAKEN-1']);

    $this->post($this->w->tenant->url('assets'), assetData($this->w, $overrides))->assertSessionHasErrors($field);
})->with([
    'duplicate code' => [['code' => 'taken-1'], 'code'],
    'bad code' => [['code' => 'has space'], 'code'],
    'unknown status' => [['status' => 'broken'], 'status'],
    'warranty before install' => [['warranty_until' => '2024-01-01'], 'warranty_until'],
    'installed in the future' => [['installed_at' => '2099-01-01', 'warranty_until' => '2099-02-01'], 'installed_at'],
]);

it('requires the location to belong to the chosen site', function () {
    $otherSite = Site::factory()->forClient($this->w->client)->create();
    $otherLocation = Location::factory()->forSite($otherSite)->create();

    $this->post($this->w->tenant->url('assets'), assetData($this->w, ['location_id' => $otherLocation->id]))
        ->assertSessionHasErrors('location_id');
});

it('rejects another tenant\'s site, location or category', function (string $field) {
    $foreign = Asset::factory()->create();

    $this->post($this->w->tenant->url('assets'), assetData($this->w, [$field => $foreign->{$field}]))
        ->assertSessionHasErrors($field);
})->with(['site_id', 'location_id', 'category_id']);

it('lists, filters by site and shows assets of this tenant only', function () {
    $otherSite = Site::factory()->forClient($this->w->client)->create();
    Asset::factory()->at($this->w->location)->create(['name' => 'Pump A']);
    Asset::factory()->at(Location::factory()->forSite($otherSite)->create())->create(['name' => 'Pump B']);
    Asset::factory()->create(['name' => 'Foreign pump']);

    $this->get($this->w->tenant->url('assets'))->assertOk()->assertSee('Pump A')->assertSee('Pump B')->assertDontSee('Foreign pump');
    $this->get($this->w->tenant->url("assets?site={$otherSite->id}"))->assertSee('Pump B')->assertDontSee('Pump A');
});

it('shows the asset with an inline QR code pointing at its scan page', function () {
    $asset = Asset::factory()->at($this->w->location)->create();

    $this->get($this->w->tenant->url("assets/{$asset->id}"))
        ->assertOk()
        ->assertSee('<svg', false)
        ->assertSee('Download label');

    expect($asset->scanUrl())->toBe($this->w->tenant->url("a/{$asset->qr_token}"));
});

it('downloads a printable SVG label', function () {
    $asset = Asset::factory()->at($this->w->location)->create(['code' => 'HVAC-9']);

    $response = $this->get($this->w->tenant->url("assets/{$asset->id}/label"));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'attachment; filename="hvac-9-qr.svg"');
    expect($response->getContent())->toStartWith('<svg')->toContain('HVAC-9');
});

it('updates and soft deletes an asset', function () {
    $asset = Asset::factory()->at($this->w->location)->create();

    $this->put($this->w->tenant->url("assets/{$asset->id}"), assetData($this->w, ['code' => $asset->code, 'status' => 'under_repair']))
        ->assertSessionHasNoErrors();
    expect($asset->fresh()->status)->toBe(AssetStatus::UnderRepair);

    $this->delete($this->w->tenant->url("assets/{$asset->id}"))->assertRedirect($this->w->tenant->url('assets'));
    $this->assertSoftDeleted($asset);
});

it('returns 404 for another tenant\'s asset', function () {
    $foreign = Asset::factory()->create();

    $this->get($this->w->tenant->url("assets/{$foreign->id}"))->assertNotFound();
    $this->get($this->w->tenant->url("assets/{$foreign->id}/label"))->assertNotFound();
});

it('lets technicians view but not manage assets', function () {
    $this->actingAs($this->w->technician);
    $asset = Asset::factory()->at($this->w->location)->create();

    $this->get($this->w->tenant->url('assets'))->assertOk();
    $this->get($this->w->tenant->url("assets/{$asset->id}/edit"))->assertForbidden();
    $this->post($this->w->tenant->url('assets'), assetData($this->w))->assertForbidden();
});

it('keeps client users out of the asset register', function () {
    $this->actingAs($this->w->clientUser);

    $this->get($this->w->tenant->url('assets'))->assertForbidden();
});
