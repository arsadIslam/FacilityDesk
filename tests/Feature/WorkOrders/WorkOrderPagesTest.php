<?php

use App\Enums\Priority;
use App\Enums\WorkOrderStatus;
use App\Livewire\WorkOrders\Create;
use App\Livewire\WorkOrders\Index;
use App\Livewire\WorkOrders\Show;
use App\Models\Location;
use App\Models\Site;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = workOrderWorld();
});

describe('pages', function () {
    it('renders the list, create and detail pages for a supervisor', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create(['title' => 'Flickering lights']);

        $this->actingAs($this->w->supervisor);

        $this->get($this->w->tenant->url('work-orders'))->assertOk()->assertSee('Flickering lights')->assertSeeLivewire(Index::class);
        $this->get($this->w->tenant->url('work-orders/create'))->assertOk()->assertSeeLivewire(Create::class);
        $this->get($this->w->tenant->url("work-orders/{$wo->id}"))->assertOk()->assertSee($wo->number);
    });

    it('forbids technicians from the create page', function () {
        $this->actingAs($this->w->technician)
            ->get($this->w->tenant->url('work-orders/create'))
            ->assertForbidden();
    });

    it('serves Livewire updates only on tenant subdomains', function () {
        $this->post('http://'.config('tenancy.domain').'/livewire/update')->assertNotFound();

        expect(app('livewire')->getUpdateUri())->toBe('/livewire/update')
            ->and(app('router')->getRoutes()->getByName('tenant.livewire.update')->gatherMiddleware())
            ->toContain('tenant', 'tenant.active', 'auth');
    });
});

describe('list', function () {
    it('never lists other tenants\' work orders', function () {
        WorkOrder::factory()->forSite($this->w->site)->create(['title' => 'Ours']);
        WorkOrder::factory()->create(['title' => 'Theirs']);

        Livewire::actingAs($this->w->admin)->test(Index::class)
            ->assertSee('Ours')
            ->assertDontSee('Theirs');
    });

    it('filters by status, priority, site and search', function () {
        $secondSite = Site::factory()->forClient($this->w->client)->create();
        WorkOrder::factory()->forSite($this->w->site)->create(['title' => 'Open critical', 'priority' => Priority::Critical, 'number' => 'WO-2026-00001']);
        WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Closed)->create(['title' => 'Closed low', 'priority' => Priority::Low]);
        WorkOrder::factory()->forSite($secondSite)->create(['title' => 'Other site']);

        Livewire::actingAs($this->w->supervisor)->test(Index::class)
            ->set('status', 'closed')->assertSee('Closed low')->assertDontSee('Open critical')
            ->set('status', '')->set('priority', 'critical')->assertSee('Open critical')->assertDontSee('Closed low')
            ->set('priority', '')->set('siteId', (string) $secondSite->id)->assertSee('Other site')->assertDontSee('Open critical')
            ->set('siteId', '')->set('search', '00001')->assertSee('Open critical')->assertDontSee('Other site');
    });

    it('filters to breached work orders', function () {
        WorkOrder::factory()->forSite($this->w->site)->create(['title' => 'Overdue job', 'resolution_due_at' => now()->subHour(), 'response_due_at' => now()->subHours(2)]);
        WorkOrder::factory()->forSite($this->w->site)->create(['title' => 'On track job']);
        WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Closed)->create(['title' => 'Closed late job', 'resolution_due_at' => now()->subHour(), 'responded_at' => now()->subDay()]);

        Livewire::actingAs($this->w->supervisor)->test(Index::class)
            ->set('breached', true)
            ->assertSee('Overdue job')
            ->assertDontSee('On track job')
            ->assertDontSee('Closed late job');
    });

    it('filters by date raised in the tenant timezone', function () {
        WorkOrder::factory()->forSite($this->w->site)->create(['title' => 'March job', 'created_at' => '2026-03-10 06:00:00']);
        // 2026-03-31 20:00 UTC is 1 April 01:30 in India.
        WorkOrder::factory()->forSite($this->w->site)->create(['title' => 'April job', 'created_at' => '2026-03-31 20:00:00']);

        Livewire::actingAs($this->w->supervisor)->test(Index::class)
            ->set('from', '2026-04-01')
            ->assertSee('April job')
            ->assertDontSee('March job');
    });

    it('sorts a technician\'s jobs by resolution due time by default', function () {
        WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['title' => 'Due later', 'resolution_due_at' => now()->addDays(2)]);
        WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['title' => 'Due sooner', 'resolution_due_at' => now()->addHour()]);

        Livewire::actingAs($this->w->technician)->test(Index::class)->assertSeeInOrder(['Due sooner', 'Due later']);
    });
});

describe('create', function () {
    it('raises a work order and redirects to it', function () {
        Livewire::actingAs($this->w->supervisor)->test(Create::class)
            ->set('site_id', (string) $this->w->site->id)
            ->set('location_id', (string) $this->w->location->id)
            ->set('category_id', (string) $this->w->category->id)
            ->assertSet('priority', 'high')
            ->set('priority', 'critical')
            ->set('title', 'Lift stuck on floor 3')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('work-orders.show', WorkOrder::sole()));

        expect(WorkOrder::sole())->priority->toBe(Priority::Critical)->title->toBe('Lift stuck on floor 3');
    });

    it('ignores the priority a client user sends and uses the category default', function () {
        Livewire::actingAs($this->w->clientUser)->test(Create::class)
            ->assertSet('site_id', (string) $this->w->site->id)
            ->set('location_id', (string) $this->w->location->id)
            ->set('category_id', (string) $this->w->category->id)
            ->set('priority', 'critical')
            ->set('title', 'Door broken')
            ->call('save')
            ->assertHasNoErrors();

        expect(WorkOrder::sole()->priority)->toBe(Priority::High);
    });

    it('only lets client users raise work orders for their client\'s sites', function () {
        $otherSite = Site::factory()->create(['tenant_id' => $this->w->tenant->id]);
        $otherLocation = Location::factory()->forSite($otherSite)->create();

        Livewire::actingAs($this->w->clientUser)->test(Create::class)
            ->set('site_id', (string) $otherSite->id)
            ->set('location_id', (string) $otherLocation->id)
            ->set('category_id', (string) $this->w->category->id)
            ->set('title', 'Not my site')
            ->call('save')
            ->assertHasErrors('site_id');

        expect(WorkOrder::count())->toBe(0);
    });

    it('rejects a location from another site or tenant', function () {
        $foreignLocation = Location::factory()->create();

        Livewire::actingAs($this->w->supervisor)->test(Create::class)
            ->set('site_id', (string) $this->w->site->id)
            ->set('location_id', (string) $foreignLocation->id)
            ->set('category_id', (string) $this->w->category->id)
            ->set('title', 'X')
            ->call('save')
            ->assertHasErrors('location_id');
    });

    it('keeps a matching location when site and location arrive in one request', function () {
        Livewire::actingAs($this->w->supervisor)->test(Create::class)
            ->set(['site_id' => (string) $this->w->site->id, 'location_id' => (string) $this->w->location->id])
            ->assertSet('location_id', (string) $this->w->location->id);
    });

    it('resets the location when the site changes', function () {
        Livewire::actingAs($this->w->supervisor)->test(Create::class)
            ->set('site_id', (string) $this->w->site->id)
            ->set('location_id', (string) $this->w->location->id)
            ->set('site_id', '')
            ->assertSet('location_id', '');
    });
});

describe('detail', function () {
    it('assigns, works and resolves through the page', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create(['location_id' => $this->w->location->id]);

        Livewire::actingAs($this->w->supervisor)->test(Show::class, ['workOrder' => $wo])
            ->set('assignee_id', (string) $this->w->technician->id)
            ->call('assign')
            ->assertHasNoErrors()
            ->assertSet('flash', "Assigned to {$this->w->technician->name}.");

        Livewire::actingAs($this->w->technician)->test(Show::class, ['workOrder' => $wo->fresh()])
            ->assertSee('Start work')
            ->assertDontSee('Cancel')
            ->call('transition', 'in_progress')
            ->call('transition', 'resolved')
            ->assertHasErrors('note')
            ->set('note', 'Rewired the switch')
            ->call('transition', 'resolved')
            ->assertHasNoErrors();

        expect($wo->fresh())->status->toBe(WorkOrderStatus::Resolved)->resolution_note->toBe('Rewired the switch');
    });

    it('shows the client only close and reopen on a resolved work order', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::Resolved)->create();

        Livewire::actingAs($this->w->clientUser)->test(Show::class, ['workOrder' => $wo])
            ->assertSee('Confirm &amp; close', false)
            ->assertSee('Reopen')
            ->assertDontSee('Reassign')
            ->call('transition', 'closed')
            ->assertHasNoErrors();

        expect($wo->fresh()->status)->toBe(WorkOrderStatus::Closed);
    });

    it('hides internal comments and the internal toggle from client users', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create();
        WorkOrderComment::factory()->create(['work_order_id' => $wo->id, 'body' => 'Visible note']);
        WorkOrderComment::factory()->internal()->create(['work_order_id' => $wo->id, 'body' => 'Secret note']);

        Livewire::actingAs($this->w->clientUser)->test(Show::class, ['workOrder' => $wo])
            ->assertSee('Visible note')
            ->assertDontSee('Secret note')
            ->assertDontSee('Internal (hidden from the client)');

        Livewire::actingAs($this->w->technician)->test(Show::class, ['workOrder' => $wo])
            ->assertSee('Secret note')
            ->set('comment_body', 'Parts ordered')
            ->set('comment_internal', true)
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertSee('Parts ordered');

        expect(WorkOrderComment::where('body', 'Parts ordered')->sole()->is_internal)->toBeTrue();
    });

    it('lists technicians covering the site and skilled in the category first', function () {
        $this->w->site->coverage()->attach($this->w->otherTechnician, ['tenant_id' => $this->w->tenant->id]);
        $this->w->otherTechnician->skills()->attach($this->w->category, ['tenant_id' => $this->w->tenant->id]);
        $wo = WorkOrder::factory()->forSite($this->w->site)->create(['category_id' => $this->w->category->id]);

        $technicians = Livewire::actingAs($this->w->supervisor)->test(Show::class, ['workOrder' => $wo])->get('technicians');

        expect($technicians->first()->is($this->w->otherTechnician))->toBeTrue()
            ->and((bool) $technicians->first()->covers_site)->toBeTrue()
            ->and((bool) $technicians->first()->has_skill)->toBeTrue();
    });

    it('forbids opening a work order the user may not see', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create();

        Livewire::actingAs($this->w->technician)->test(Show::class, ['workOrder' => $wo])->assertForbidden();
    });
});
