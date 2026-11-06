<?php

use App\Enums\WorkOrderSource;
use App\Enums\WorkOrderStatus;
use App\Livewire\WorkOrders\Create;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Site;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->asset = Asset::factory()->at($this->w->location)->create(['name' => 'Chiller 1', 'category_id' => $this->w->category->id]);
});

it('opens the asset from its QR token with its open work orders', function () {
    WorkOrder::factory()->forSite($this->w->site)->create(['asset_id' => $this->asset->id, 'title' => 'Chiller noisy']);
    WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Closed)->create(['asset_id' => $this->asset->id, 'title' => 'Old fix']);

    $this->actingAs($this->w->supervisor)
        ->get($this->w->tenant->url("a/{$this->asset->qr_token}"))
        ->assertOk()
        ->assertSee('Chiller 1')
        ->assertSee('Chiller noisy')
        ->assertDontSee('Old fix')
        ->assertSee(route('work-orders.create', ['asset' => $this->asset->qr_token]), false);
});

it('requires login to scan', function () {
    $this->get($this->w->tenant->url("a/{$this->asset->qr_token}"))->assertRedirect($this->w->tenant->url('login'));
});

it('returns 404 for a token from another tenant or an unknown token', function () {
    $foreign = Asset::factory()->create();
    $this->actingAs($this->w->supervisor);

    $this->get($this->w->tenant->url("a/{$foreign->qr_token}"))->assertNotFound();
    $this->get($this->w->tenant->url('a/01JUNKJUNKJUNKJUNKJUNKJUNK'))->assertNotFound();
});

it('lets client users scan assets at their own client\'s sites only', function () {
    $otherSite = Site::factory()->create(['tenant_id' => $this->w->tenant->id]);
    $elsewhere = Asset::factory()->at(Location::factory()->forSite($otherSite)->create())->create();

    $this->actingAs($this->w->clientUser);

    $this->get($this->w->tenant->url("a/{$this->asset->qr_token}"))->assertOk()->assertSee('Report a problem');
    $this->get($this->w->tenant->url("a/{$elsewhere->qr_token}"))->assertForbidden();
});

it('only shows a client user the work orders they may see', function () {
    $otherSite = Site::factory()->create(['tenant_id' => $this->w->tenant->id]);
    // Edge case: an asset moved sites keeps its history; the client must not see another client's work orders.
    WorkOrder::factory()->forSite($otherSite)->create(['asset_id' => $this->asset->id, 'title' => 'Other client job']);

    $this->actingAs($this->w->clientUser)
        ->get($this->w->tenant->url("a/{$this->asset->qr_token}"))
        ->assertDontSee('Other client job');
});

it('prefills a work order from the scanned asset and marks it as a QR report', function () {
    Livewire::actingAs($this->w->clientUser)
        ->withQueryParams(['asset' => $this->asset->qr_token])
        ->test(Create::class)
        ->assertSet('site_id', (string) $this->w->site->id)
        ->assertSet('location_id', (string) $this->w->location->id)
        ->assertSet('category_id', (string) $this->w->category->id)
        ->assertSet('asset_id', (string) $this->asset->id)
        ->assertSee('Chiller 1')
        ->set('title', 'Chiller leaking')
        ->call('save')
        ->assertHasNoErrors();

    expect(WorkOrder::sole())
        ->asset_id->toBe($this->asset->id)
        ->source->toBe(WorkOrderSource::Qr);
});

it('drops the asset when the site is changed to one it is not in', function () {
    $otherSite = Site::factory()->forClient($this->w->client)->create();

    Livewire::actingAs($this->w->supervisor)
        ->withQueryParams(['asset' => $this->asset->qr_token])
        ->test(Create::class)
        ->set('site_id', (string) $otherSite->id)
        ->assertSet('asset_id', '')
        ->assertSet('fromQr', false);
});

it('rejects an asset id from another site even if tampered in', function () {
    $otherSite = Site::factory()->forClient($this->w->client)->create();
    $otherAsset = Asset::factory()->at(Location::factory()->forSite($otherSite)->create())->create();

    Livewire::actingAs($this->w->supervisor)->test(Create::class)
        ->set('site_id', (string) $this->w->site->id)
        ->set('location_id', (string) $this->w->location->id)
        ->set('category_id', (string) $this->w->category->id)
        ->set('asset_id', (string) $otherAsset->id)
        ->set('title', 'X')
        ->call('save')
        ->assertHasErrors('asset_id');
});

it('returns 404 when prefilling from another tenant\'s token', function () {
    $foreign = Asset::factory()->create();

    expect(fn () => Livewire::actingAs($this->w->supervisor)->withQueryParams(['asset' => $foreign->qr_token])->test(Create::class))
        ->toThrow(ModelNotFoundException::class);
});
