<?php

use App\Actions\Locations\SaveLocation;
use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Site;
use App\Services\TenantContext;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    actingAsRole(UserRole::TenantAdmin, $this->tenant);
    $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
});

function addLocation(Site $site, string $name, ?Location $parent = null, LocationType $type = LocationType::Room): Location
{
    return app(TenantContext::class)->run(
        $site->tenant()->sole(),
        fn () => app(SaveLocation::class)->handle($site, $type, $name, $parent?->id),
    );
}

it('builds the breadcrumb path from the parent', function () {
    $this->post($this->tenant->url("sites/{$this->site->id}/locations"), ['type' => 'building', 'name' => 'Block A'])
        ->assertSessionHasNoErrors();
    $block = Location::sole();

    $this->post($this->tenant->url("sites/{$this->site->id}/locations"), ['type' => 'floor', 'name' => '3rd Floor', 'parent_id' => $block->id]);
    $floor = Location::where('name', '3rd Floor')->sole();

    $this->post($this->tenant->url("sites/{$this->site->id}/locations"), ['type' => 'room', 'name' => 'Room 304', 'parent_id' => $floor->id]);

    expect(Location::where('name', 'Room 304')->sole()->path)->toBe('Block A / 3rd Floor / Room 304')
        ->and($block->tenant_id)->toBe($this->tenant->id);
});

it('renders the tree on the site page', function () {
    $block = addLocation($this->site, 'Block A', type: LocationType::Building);
    addLocation($this->site, 'Lobby', $block, LocationType::Area);

    $this->get($this->tenant->url("sites/{$this->site->id}"))
        ->assertOk()
        ->assertSeeInOrder(['Block A', 'Lobby']);
});

it('refreshes descendant paths when a node is renamed', function () {
    $block = addLocation($this->site, 'Block A', type: LocationType::Building);
    $floor = addLocation($this->site, '3rd Floor', $block, LocationType::Floor);
    $room = addLocation($this->site, 'Room 304', $floor);

    $this->put($this->tenant->url("sites/{$this->site->id}/locations/{$block->id}"), ['type' => 'building', 'name' => 'Block Alpha'])
        ->assertSessionHasNoErrors();

    expect($floor->fresh()->path)->toBe('Block Alpha / 3rd Floor')
        ->and($room->fresh()->path)->toBe('Block Alpha / 3rd Floor / Room 304');
});

it('moves a subtree under a new parent', function () {
    $blockA = addLocation($this->site, 'Block A', type: LocationType::Building);
    $blockB = addLocation($this->site, 'Block B', type: LocationType::Building);
    $floor = addLocation($this->site, 'Ground', $blockA, LocationType::Floor);
    $room = addLocation($this->site, 'Pantry', $floor);

    $this->put($this->tenant->url("sites/{$this->site->id}/locations/{$floor->id}"), ['type' => 'floor', 'name' => 'Ground', 'parent_id' => $blockB->id])
        ->assertSessionHasNoErrors();

    expect($room->fresh()->path)->toBe('Block B / Ground / Pantry');
});

it('prevents moving a location under its own descendant', function () {
    $block = addLocation($this->site, 'Block A', type: LocationType::Building);
    $floor = addLocation($this->site, 'Floor 1', $block, LocationType::Floor);

    $this->put($this->tenant->url("sites/{$this->site->id}/locations/{$block->id}"), ['type' => 'building', 'name' => 'Block A', 'parent_id' => $floor->id])
        ->assertSessionHasErrors('parent_id');

    $this->put($this->tenant->url("sites/{$this->site->id}/locations/{$block->id}"), ['type' => 'building', 'name' => 'Block A', 'parent_id' => $block->id])
        ->assertSessionHasErrors('parent_id');
});

it('rejects a parent from another site', function () {
    $otherSite = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreignParent = addLocation($otherSite, 'Elsewhere', type: LocationType::Building);

    $this->post($this->tenant->url("sites/{$this->site->id}/locations"), ['type' => 'room', 'name' => 'Room', 'parent_id' => $foreignParent->id])
        ->assertSessionHasErrors('parent_id');
});

it('returns 404 for a location addressed through the wrong site', function () {
    $otherSite = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    $location = addLocation($otherSite, 'Elsewhere', type: LocationType::Building);

    $this->get($this->tenant->url("sites/{$this->site->id}/locations/{$location->id}/edit"))->assertNotFound();
});

it('deletes a leaf but not a location with children', function () {
    $block = addLocation($this->site, 'Block A', type: LocationType::Building);
    $room = addLocation($this->site, 'Room 1', $block);

    $this->delete($this->tenant->url("sites/{$this->site->id}/locations/{$block->id}"))->assertSessionHasErrors('location');
    $this->assertNotSoftDeleted($block);

    $this->delete($this->tenant->url("sites/{$this->site->id}/locations/{$room->id}"))->assertSessionHasNoErrors();
    $this->assertSoftDeleted($room);
});

it('rejects names containing the path separator', function () {
    $this->post($this->tenant->url("sites/{$this->site->id}/locations"), ['type' => 'room', 'name' => 'A/B'])
        ->assertSessionHasErrors('name');
});

it('forbids supervisors from changing locations', function () {
    actingAsRole(UserRole::Supervisor, $this->tenant);

    $this->post($this->tenant->url("sites/{$this->site->id}/locations"), ['type' => 'room', 'name' => 'Room'])
        ->assertForbidden();
});
