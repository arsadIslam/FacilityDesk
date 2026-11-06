<?php

use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->w = workOrderWorld();
    Sanctum::actingAs($this->w->admin);

    $this->foreign = WorkOrder::factory()->create();
    $this->foreignAsset = Asset::factory()->create();
});

it('returns a bare 404 for every other-tenant record', function (string $method, Closure $path) {
    $response = $this->json($method, api($path($this)), ['status' => 'in_progress', 'assignee_id' => 1, 'body' => 'x', 'kind' => 'after']);

    $response->assertNotFound()->assertExactJson(['message' => 'Not found.']);
})->with([
    'show work order' => ['GET', fn ($t) => "work-orders/{$t->foreign->id}"],
    'assign' => ['PATCH', fn ($t) => "work-orders/{$t->foreign->id}/assign"],
    'status' => ['PATCH', fn ($t) => "work-orders/{$t->foreign->id}/status"],
    'comment' => ['POST', fn ($t) => "work-orders/{$t->foreign->id}/comments"],
    'media' => ['POST', fn ($t) => "work-orders/{$t->foreign->id}/media"],
    'site locations' => ['GET', fn ($t) => "sites/{$t->foreign->site_id}/locations"],
    'asset by QR token' => ['GET', fn ($t) => "assets/qr/{$t->foreignAsset->qr_token}"],
    'attachment' => ['GET', fn ($t) => 'attachments/'.Attachment::factory()->forWorkOrder($t->foreign)->create()->id],
]);

it('never lists other tenants\' rows', function () {
    WorkOrder::factory()->forSite($this->w->site)->create();

    $ids = collect($this->getJson(api('work-orders'))->json('data'))->pluck('id');
    $sites = collect($this->getJson(api('sites'))->json('data'))->pluck('id');

    expect($ids)->not->toContain($this->foreign->id)
        ->and($sites)->toContain($this->w->site->id)->not->toContain($this->foreign->site_id);
});

it('scopes sites to what each role may use', function () {
    $otherClientSite = Site::factory()->create(['tenant_id' => $this->w->tenant->id]);
    $covered = Site::factory()->forClient($this->w->client)->create();
    $covered->coverage()->attach($this->w->technician, ['tenant_id' => $this->w->tenant->id]);

    Sanctum::actingAs($this->w->clientUser);
    expect(collect($this->getJson(api('sites'))->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->w->site->id, $covered->id])->sort()->values()->all());
    $this->getJson(api("sites/{$otherClientSite->id}/locations"))->assertForbidden();

    Sanctum::actingAs($this->w->technician);
    expect(collect($this->getJson(api('sites'))->json('data'))->pluck('id')->all())->toBe([$covered->id]);
});

it('lists a site\'s locations', function () {
    Location::factory()->forSite($this->w->site)->create(['name' => 'Annex', 'path' => 'Annex']);

    $this->getJson(api("sites/{$this->w->site->id}/locations"))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'parent_id', 'type', 'name', 'path']]]);
});

it('looks up an asset by QR token with its open work orders', function () {
    $asset = Asset::factory()->at($this->w->location)->create();
    WorkOrder::factory()->forSite($this->w->site)->create(['asset_id' => $asset->id]);
    WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Closed)->create(['asset_id' => $asset->id]);

    $this->getJson(api("assets/qr/{$asset->qr_token}"))
        ->assertOk()
        ->assertJsonPath('data.code', $asset->code)
        ->assertJsonCount(1, 'data.open_work_orders');
});

it('gives technicians a cursor-paginated job list, soonest due first', function () {
    $later = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['resolution_due_at' => now()->addDays(2)]);
    $sooner = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::InProgress)->create(['resolution_due_at' => now()->addHour()]);
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::Closed)->create();
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->otherTechnician)->create();
    Sanctum::actingAs($this->w->technician);

    $this->getJson(api('technician/jobs'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $sooner->id)
        ->assertJsonPath('data.1.id', $later->id)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor']]);
});

it('keeps the job list for technicians', function () {
    $this->getJson(api('technician/jobs'))->assertForbidden();
});

it('returns the dashboard summary to supervisors and admins only', function () {
    WorkOrder::factory()->forSite($this->w->site)->count(2)->create();
    WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Resolved)->create(['resolved_at' => now(), 'is_resolution_breached' => true]);
    WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Closed)->create(['resolved_at' => now()]);

    $this->getJson(api('dashboard/summary'))
        ->assertOk()
        ->assertJsonPath('data.open', 2)
        ->assertJsonPath('data.unassigned', 2)
        ->assertJsonPath('data.resolved_this_month', 2)
        ->assertJsonPath('data.sla_compliance_percent', 50);

    Sanctum::actingAs($this->w->technician);
    $this->getJson(api('dashboard/summary'))->assertForbidden();
});

it('does not let a super admin token act on tenant data', function () {
    // Outside any tenant, as a platform user would be created.
    app(TenantContext::class)->clear();
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->getJson(api('work-orders'))->assertNotFound();
});

it('does not let a token-holder pick a tenant through the request', function () {
    $this->getJson(api('work-orders?tenant_id='.$this->foreign->tenant_id))->assertOk()
        ->assertJsonMissing(['id' => $this->foreign->id]);
});

it('treats a user of another role in the same tenant like any other user', function () {
    $client = userWithRole(UserRole::ClientUser, $this->w->tenant);
    Sanctum::actingAs($client);

    // No client assigned: sees nothing.
    $this->getJson(api('work-orders'))->assertOk()->assertJsonCount(0, 'data');
});
