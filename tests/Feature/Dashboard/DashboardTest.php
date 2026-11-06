<?php

use App\Actions\WorkOrders\AssignWorkOrder;
use App\Enums\WorkOrderStatus;
use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\Site;
use App\Models\WorkOrder;
use App\Services\DashboardStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->travelTo(CarbonImmutable::parse('2026-05-20 06:30:00', 'UTC')); // 12:00 IST
    $this->stats = app(DashboardStats::class);
    $this->may = fn () => $this->stats->forPeriod($this->w->tenant, CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31'));
});

it('computes open, breached, raised, closed and SLA compliance for the period', function () {
    $make = fn (array $a) => WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-05-10 05:00:00', 'resolution_due_at' => now()->addDay(), 'response_due_at' => now()->addDay(), ...$a]);

    $make(['status' => WorkOrderStatus::Open]);
    $make(['status' => WorkOrderStatus::InProgress, 'responded_at' => '2026-05-10 06:00:00', 'resolution_due_at' => now()->subHour()]);
    $make(['status' => WorkOrderStatus::Resolved, 'responded_at' => '2026-05-10 06:00:00', 'resolved_at' => '2026-05-11 06:00:00']);
    $make(['status' => WorkOrderStatus::Closed, 'responded_at' => '2026-05-10 06:00:00', 'resolved_at' => '2026-05-12 06:00:00', 'closed_at' => '2026-05-12 08:00:00', 'is_resolution_breached' => true, 'is_response_breached' => true]);
    $make(['status' => WorkOrderStatus::Closed, 'created_at' => '2026-04-01 05:00:00', 'resolved_at' => '2026-04-02 05:00:00', 'closed_at' => '2026-04-02 06:00:00']); // last month
    WorkOrder::factory()->create(['created_at' => '2026-05-10 05:00:00']); // other tenant

    $s = ($this->may)();

    expect($s['now'])->toMatchArray(['open' => 2, 'breached' => 1])
        ->and($s['period'])->toMatchArray([
            'created' => 4,
            'resolved' => 2,
            'closed' => 1,
            'resolution_compliance' => 50.0,
            'response_compliance' => 66.7,
        ]);
});

it('uses the tenant\'s local days for the period', function () {
    // 30 Apr 20:00 UTC is already 1 May in India.
    WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-04-30 20:00:00']);
    WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-04-30 17:00:00']);

    expect(($this->may)()['period']['created'])->toBe(1);
});

it('breaks down jobs per technician and top categories', function () {
    $plumbing = Category::factory()->forTenant($this->w->tenant)->create(['name' => 'Plumbing']);
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::InProgress)->count(2)->create(['category_id' => $plumbing->id, 'created_at' => '2026-05-05 05:00:00']);
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::Resolved)->create(['resolved_at' => '2026-05-06 05:00:00', 'is_resolution_breached' => true, 'created_at' => '2026-05-05 05:00:00']);
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->otherTechnician, WorkOrderStatus::Assigned)->create(['created_at' => '2026-05-05 05:00:00']);

    $s = ($this->may)();

    expect($s['technicians'][0])->toBe(['id' => $this->w->technician->id, 'name' => $this->w->technician->name, 'open' => 2, 'resolved' => 1, 'breached' => 1])
        ->and($s['technicians'][1]['open'])->toBe(1)
        ->and($s['top_categories'][0])->toBe(['name' => 'Plumbing', 'total' => 2]);
});

it('filters by site', function () {
    $other = Site::factory()->forClient($this->w->client)->create();
    WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-05-05 05:00:00']);
    WorkOrder::factory()->forSite($other)->count(2)->create(['created_at' => '2026-05-05 05:00:00']);

    $s = $this->stats->forPeriod($this->w->tenant, CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31'), $other->id);

    expect($s['period']['created'])->toBe(2)->and($s['now']['open'])->toBe(2);
});

it('serves repeat requests from the cache for five minutes', function () {
    WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-05-05 05:00:00']);
    ($this->may)();

    DB::enableQueryLog();
    $s = ($this->may)();

    expect(DB::getQueryLog())->toBe([])->and($s['period']['created'])->toBe(1);

    // A raw insert fires no event, so only the TTL expires the cached numbers.
    WorkOrder::factory()->forSite($this->w->site)->createQuietly(['created_at' => '2026-05-05 05:00:00']);
    expect(($this->may)()['period']['created'])->toBe(1);

    $this->travel(DashboardStats::TTL_SECONDS + 1)->seconds();
    expect(($this->may)()['period']['created'])->toBe(2);
});

it('is busted by work order events', function () {
    $wo = WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-05-05 05:00:00']);
    expect(($this->may)()['technicians'])->toBe([]);

    app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);

    expect(($this->may)()['technicians'][0]['id'])->toBe($this->w->technician->id);
});

it('keeps tenants\' caches apart', function () {
    $other = tenantWithRoles(['timezone' => 'Asia/Kolkata']);
    WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-05-05 05:00:00']);
    ($this->may)();

    enterTenant($other);
    expect($this->stats->forPeriod($other, CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31'))['period']['created'])->toBe(0);
});

it('shows managers the tenant dashboard with filters', function () {
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['created_at' => '2026-05-05 05:00:00']);

    Livewire::actingAs($this->w->supervisor)->test(Dashboard::class)
        ->assertSet('from', '2026-05-01')
        ->assertSet('to', '2026-05-20')
        ->assertSee('Jobs per technician')
        ->assertSee($this->w->technician->name)
        ->set('siteId', (string) Site::factory()->forClient($this->w->client)->create()->id)
        ->assertSee('No assigned work yet.');
});

it('shows technicians and client users a personal summary', function () {
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['resolution_due_at' => now()->subHour()]);

    Livewire::actingAs($this->w->technician)->test(Dashboard::class)
        ->assertSee('Your open jobs')
        ->assertDontSee('Jobs per technician')
        ->assertViewHas('isManager', false);

    Livewire::actingAs($this->w->clientUser)->test(Dashboard::class)->assertSee('Waiting for your confirmation');
});

it('feeds the API summary from the same cached numbers', function () {
    WorkOrder::factory()->forSite($this->w->site)->count(2)->create();

    expect($this->stats->summary($this->w->tenant, CarbonImmutable::now()))->toMatchArray(['open' => 2, 'unassigned' => 2]);
});
