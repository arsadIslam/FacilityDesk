<?php

use App\Actions\WorkOrders\CreateWorkOrder;
use App\Enums\Priority;
use App\Enums\WorkOrderSource;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderCreated;
use App\Models\Location;
use App\Models\Site;
use App\Models\SlaPolicy;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->create = app(CreateWorkOrder::class);
});

it('creates an open work order with a number, SLA and history', function () {
    $this->travelTo(now()->setDateTime(2026, 5, 4, 10, 0));
    SlaPolicy::factory()->create(['tenant_id' => $this->w->tenant->id, 'priority' => Priority::High, 'response_minutes' => 30, 'resolution_minutes' => 300]);

    $wo = $this->create->handle($this->w->supervisor, $this->w->site, $this->w->location, $this->w->category, 'Leaking tap');

    expect($wo->fresh())
        ->number->toBe('WO-2026-00001')
        ->status->toBe(WorkOrderStatus::Open)
        ->priority->toBe(Priority::High)
        ->source->toBe(WorkOrderSource::Web)
        ->tenant_id->toBe($this->w->tenant->id)
        ->raised_by->toBe($this->w->supervisor->id)
        ->and($wo->response_due_at->toDateTimeString())->toBe('2026-05-04 10:30:00')
        ->and($wo->resolution_due_at->toDateTimeString())->toBe('2026-05-04 15:00:00');

    $history = $wo->statusHistories()->sole();
    expect($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe(WorkOrderStatus::Open)
        ->and($history->changed_by)->toBe($this->w->supervisor->id);
});

it('uses the category default priority unless one is chosen', function () {
    $default = $this->create->handle($this->w->supervisor, $this->w->site, $this->w->location, $this->w->category, 'A');
    $chosen = $this->create->handle($this->w->supervisor, $this->w->site, $this->w->location, $this->w->category, 'B', priority: Priority::Critical);

    expect($default->priority)->toBe(Priority::High)
        ->and($chosen->priority)->toBe(Priority::Critical);
});

it('falls back to platform SLA defaults when the tenant has no policy row', function () {
    $this->travelTo(now()->setDateTime(2026, 5, 4, 10, 0));
    SlaPolicy::query()->delete();

    $wo = $this->create->handle($this->w->supervisor, $this->w->site, $this->w->location, $this->w->category, 'A', priority: Priority::Critical);

    expect($wo->response_due_at->toDateTimeString())->toBe('2026-05-04 10:15:00');
});

it('dispatches WorkOrderCreated', function () {
    Event::fake([WorkOrderCreated::class]);

    $wo = $this->create->handle($this->w->clientUser, $this->w->site, $this->w->location, $this->w->category, 'Broken door');

    Event::assertDispatched(WorkOrderCreated::class, fn (WorkOrderCreated $e) => $e->workOrder->is($wo));
});

it('rejects a location from another site', function () {
    $otherSite = Site::factory()->forClient($this->w->client)->create();
    $otherLocation = Location::factory()->forSite($otherSite)->create();

    $this->create->handle($this->w->supervisor, $this->w->site, $otherLocation, $this->w->category, 'A');
})->throws(ValidationException::class);

it('does not let technicians raise work orders', function () {
    $this->create->handle($this->w->technician, $this->w->site, $this->w->location, $this->w->category, 'A');
})->throws(AuthorizationException::class);

it('numbers work orders sequentially', function () {
    $this->travelTo(now()->setDateTime(2026, 5, 4, 10, 0));

    foreach (range(1, 3) as $i) {
        $this->create->handle($this->w->supervisor, $this->w->site, $this->w->location, $this->w->category, "WO {$i}");
    }

    expect(WorkOrder::orderBy('id')->pluck('number')->all())->toBe(['WO-2026-00001', 'WO-2026-00002', 'WO-2026-00003']);
});
