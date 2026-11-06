<?php

use App\Actions\WorkOrders\ChangeStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Events\SlaBreached;
use App\Listeners\NotifySupervisorOfBreach;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Notifications\SlaBreachAlert;
use App\Services\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->travelTo(now()->setDateTime(2026, 5, 4, 12, 0));

    $this->make = fn (array $attributes = []) => WorkOrder::factory()->forSite($this->w->site)->create([
        'response_due_at' => now()->addHour(),
        'resolution_due_at' => now()->addHours(8),
        ...$attributes,
    ]);

    // The scheduler runs with no tenant in context.
    app(TenantContext::class)->clear();
});

it('flags a missed response and resolution and records the alert time', function () {
    $wo = ($this->make)(['response_due_at' => now()->subMinutes(10), 'resolution_due_at' => now()->subMinute()]);

    $this->artisan('sla:check-breaches')->expectsOutputToContain('1 work order(s) newly breached')->assertSuccessful();

    expect($wo->fresh())
        ->is_response_breached->toBeTrue()
        ->is_resolution_breached->toBeTrue()
        ->and($wo->fresh()->breach_notified_at->equalTo(now()))->toBeTrue();
});

it('sends exactly one alert even when run twice', function () {
    Event::fake([SlaBreached::class]);
    ($this->make)(['response_due_at' => now()->subMinute()]);

    $this->artisan('sla:check-breaches');
    $this->artisan('sla:check-breaches')->expectsOutputToContain('0 work order(s) newly breached');

    Event::assertDispatchedTimes(SlaBreached::class, 1);
});

it('alerts again only when a second target is missed later', function () {
    Event::fake([SlaBreached::class]);
    $wo = ($this->make)(['response_due_at' => now()->subMinute(), 'resolution_due_at' => now()->addHour()]);

    $this->artisan('sla:check-breaches');
    $this->travel(2)->hours();
    $this->artisan('sla:check-breaches');
    $this->artisan('sla:check-breaches');

    Event::assertDispatchedTimes(SlaBreached::class, 2);
    Event::assertDispatched(SlaBreached::class, fn ($e) => $e->breaches === ['response']);
    Event::assertDispatched(SlaBreached::class, fn ($e) => $e->breaches === ['resolution'] && $e->workOrder->is($wo));
});

it('leaves work orders that are on time alone', function () {
    Event::fake([SlaBreached::class]);
    $wo = ($this->make)();

    $this->artisan('sla:check-breaches');

    expect($wo->fresh())->is_response_breached->toBeFalse()->is_resolution_breached->toBeFalse();
    Event::assertNotDispatched(SlaBreached::class);
});

it('ignores finished, cancelled and on-hold work orders', function (WorkOrderStatus $status, array $extra) {
    Event::fake([SlaBreached::class]);
    $wo = ($this->make)(['status' => $status, 'responded_at' => now()->subHours(3), 'resolution_due_at' => now()->subHour(), ...$extra]);

    $this->artisan('sla:check-breaches');

    expect($wo->fresh()->is_resolution_breached)->toBeFalse();
    Event::assertNotDispatched(SlaBreached::class);
})->with([
    'resolved' => [WorkOrderStatus::Resolved, ['resolved_at' => now()->subHours(2)]],
    'closed' => [WorkOrderStatus::Closed, ['resolved_at' => now()->subHours(2)]],
    'cancelled' => [WorkOrderStatus::Cancelled, ['responded_at' => null, 'response_due_at' => now()->subHours(5)]],
    'on hold (clock paused)' => [WorkOrderStatus::OnHold, []],
]);

it('does not double-alert a response already answered', function () {
    Event::fake([SlaBreached::class]);
    ($this->make)(['status' => WorkOrderStatus::InProgress, 'responded_at' => now()->subMinutes(5), 'response_due_at' => now()->subMinutes(30)]);

    $this->artisan('sla:check-breaches');

    Event::assertNotDispatched(SlaBreached::class);
});

it('skips suspended tenants', function () {
    Event::fake([SlaBreached::class]);
    ($this->make)(['response_due_at' => now()->subMinute()]);
    $this->w->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    $this->artisan('sla:check-breaches');

    Event::assertNotDispatched(SlaBreached::class);
});

it('checks every tenant in one run', function () {
    Event::fake([SlaBreached::class]);
    ($this->make)(['response_due_at' => now()->subMinute()]);
    $other = WorkOrder::factory()->create(['response_due_at' => now()->subMinute(), 'resolution_due_at' => now()->addDay()]);

    $this->artisan('sla:check-breaches');

    Event::assertDispatchedTimes(SlaBreached::class, 2);
    expect($other->fresh()->is_response_breached)->toBeTrue();
});

it('alerts the site supervisor and the tenant admins, and nobody else', function () {
    Notification::fake();
    $this->w->site->update(['supervisor_id' => $this->w->supervisor->id]);
    $otherSupervisor = userWithRole(UserRole::Supervisor, $this->w->tenant);
    $foreignAdmin = userWithRole(UserRole::TenantAdmin);
    app(TenantContext::class)->clear();

    ($this->make)(['response_due_at' => now()->subMinute()]);
    $this->artisan('sla:check-breaches');

    Notification::assertSentTo([$this->w->supervisor, $this->w->admin], SlaBreachAlert::class, fn ($n) => $n->breaches === ['response']);
    Notification::assertNotSentTo([$otherSupervisor, $this->w->technician, $foreignAdmin], SlaBreachAlert::class);
});

it('queues the alert on the notifications queue with tenant context', function () {
    Event::fake();

    Event::assertListening(SlaBreached::class, NotifySupervisorOfBreach::class);
    expect((new NotifySupervisorOfBreach)->queue)->toBe('notifications');
});

it('is scheduled every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'sla:check-breaches'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('flags late responses and resolutions when they happen, not just while waiting', function () {
    $wo = ($this->make)(['status' => WorkOrderStatus::Assigned, 'assigned_to' => $this->w->technician->id, 'response_due_at' => now()->subMinutes(5)]);
    enterTenant($this->w->tenant);

    $wo = app(ChangeStatus::class)->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);
    expect($wo->is_response_breached)->toBeTrue()->and($wo->is_resolution_breached)->toBeFalse();

    $this->travel(9)->hours();
    $wo = app(ChangeStatus::class)->handle($wo, WorkOrderStatus::Resolved, $this->w->technician, 'Fixed late');
    expect($wo->is_resolution_breached)->toBeTrue();
});

it('lists flagged on-hold work orders as breached', function () {
    $wo = ($this->make)(['status' => WorkOrderStatus::OnHold, 'responded_at' => now()->subHours(3), 'resolution_due_at' => now()->subHour()]);
    enterTenant($this->w->tenant);

    expect(WorkOrder::breached()->pluck('id')->all())->toBe([]);

    $wo->forceFill(['is_resolution_breached' => true])->save();

    expect(WorkOrder::breached()->pluck('id')->all())->toBe([$wo->id]);
});
