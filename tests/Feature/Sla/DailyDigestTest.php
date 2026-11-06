<?php

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Jobs\SendDailyDigest;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Notifications\DailyDigest;
use App\Services\DailyDigestBuilder;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->w = workOrderWorld(); // Asia/Kolkata, UTC+5:30
    app(TenantContext::class)->clear();
});

it('queues the digest only for tenants where it is 08:00 locally', function () {
    Queue::fake();
    $dubai = Tenant::factory()->create(['timezone' => 'Asia/Dubai']); // UTC+4

    // 02:45 UTC = 08:15 in India, 06:45 in Dubai.
    $this->travelTo(CarbonImmutable::parse('2026-05-04 02:45:00', 'UTC'));
    $this->artisan('digest:daily')->assertSuccessful();

    Queue::assertPushedOn('notifications', SendDailyDigest::class, fn ($job) => $job->tenantId === $this->w->tenant->id);
    Queue::assertNotPushed(SendDailyDigest::class, fn ($job) => $job->tenantId === $dubai->id);
});

it('sends at most one digest per tenant per local day', function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-05-04 02:45:00', 'UTC'));

    $this->artisan('digest:daily');
    $this->artisan('digest:daily');

    Queue::assertPushed(SendDailyDigest::class, 1);
});

it('skips suspended tenants', function () {
    Queue::fake();
    $this->w->tenant->forceFill(['status' => TenantStatus::Suspended])->save();
    $this->travelTo(CarbonImmutable::parse('2026-05-04 02:45:00', 'UTC'));

    $this->artisan('digest:daily');

    Queue::assertNothingPushed();
});

it('can be forced for one tenant at any hour', function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-05-04 15:00:00', 'UTC'));

    $this->artisan('digest:daily', ['--tenant' => $this->w->tenant->slug, '--force' => true]);

    Queue::assertPushed(SendDailyDigest::class, 1);
});

it('mails active supervisors only', function () {
    Notification::fake();
    $inactive = userWithRole(UserRole::Supervisor, $this->w->tenant, ['is_active' => false]);
    app(TenantContext::class)->clear();

    $job = new SendDailyDigest($this->w->tenant->id);
    $job->middleware()[0]->handle($job, fn ($job) => app()->call([$job, 'handle']));

    Notification::assertSentTo($this->w->supervisor, DailyDigest::class, fn ($n, $channels) => $channels === ['mail']);
    Notification::assertNotSentTo([$inactive, $this->w->admin, $this->w->technician], DailyDigest::class);
});

it('counts open, unassigned, breached, due today and resolved yesterday in local days', function () {
    $now = CarbonImmutable::parse('2026-05-04 03:00:00', 'UTC'); // 08:30 IST, 4 May
    $this->travelTo($now);
    $make = fn (array $a) => WorkOrder::factory()->forSite($this->w->site)->create([
        'response_due_at' => $now->addDay(), 'resolution_due_at' => $now->addDays(2), 'responded_at' => $now, ...$a,
    ]);

    $make(['status' => WorkOrderStatus::Open, 'responded_at' => null]);
    $make(['status' => WorkOrderStatus::InProgress, 'resolution_due_at' => '2026-05-04 17:00:00']);  // 22:30 IST today
    $make(['status' => WorkOrderStatus::InProgress, 'resolution_due_at' => '2026-05-03 10:00:00', 'title' => 'Very late']);
    $make(['status' => WorkOrderStatus::Resolved, 'resolved_at' => '2026-05-03 12:00:00']);  // 17:30 IST yesterday
    $make(['status' => WorkOrderStatus::Closed, 'resolved_at' => '2026-05-01 12:00:00']);
    WorkOrder::factory()->create(['status' => WorkOrderStatus::Open]); // another tenant

    enterTenant($this->w->tenant);
    $stats = app(DailyDigestBuilder::class)->build($this->w->tenant, $now);

    expect($stats)->toMatchArray([
        'date' => '2026-05-04',
        'open' => 3,
        'unassigned' => 1,
        'breached' => 1,
        'due_today' => 1,
        'resolved_yesterday' => 1,
    ])->and($stats['overdue'][0]['title'])->toBe('Very late');
});

it('is scheduled hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'digest:daily'));

    expect($event->expression)->toBe('0 * * * *');
});
