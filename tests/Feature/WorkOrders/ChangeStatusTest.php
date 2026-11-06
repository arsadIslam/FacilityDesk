<?php

use App\Actions\WorkOrders\AssignWorkOrder;
use App\Actions\WorkOrders\ChangeStatus;
use App\Actions\WorkOrders\CloseWorkOrder;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderStatusChanged;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->change = app(ChangeStatus::class);
    $this->assign = app(AssignWorkOrder::class);

    $this->travelTo(now()->setDateTime(2026, 5, 4, 9, 0));
    $this->wo = WorkOrder::factory()->forSite($this->w->site)->create([
        'location_id' => $this->w->location->id,
        'category_id' => $this->w->category->id,
        'raised_by' => $this->w->clientUser->id,
        'response_due_at' => now()->addHour(),
        'resolution_due_at' => now()->addHours(8),
    ]);
});

function errorsFor(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Expected a ValidationException.');
}

it('runs the full happy path with side effects and history', function () {
    $wo = $this->assign->handle($this->wo, $this->w->technician, $this->w->supervisor);
    expect($wo)->status->toBe(WorkOrderStatus::Assigned)
        ->assigned_to->toBe($this->w->technician->id)
        ->assigned_by->toBe($this->w->supervisor->id);

    $this->travel(20)->minutes();
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);
    expect($wo->responded_at->toDateTimeString())->toBe('2026-05-04 09:20:00');

    $this->travel(1)->hour();
    $wo = $this->change->handle($wo, WorkOrderStatus::Resolved, $this->w->technician, 'Replaced the washer');
    expect($wo)->resolution_note->toBe('Replaced the washer')
        ->and($wo->resolved_at->toDateTimeString())->toBe('2026-05-04 10:20:00');

    $wo = app(CloseWorkOrder::class)->handle($wo, $this->w->clientUser);
    expect($wo)->status->toBe(WorkOrderStatus::Closed)->closed_at->not->toBeNull();

    expect($wo->statusHistories()->orderBy('id')->pluck('to_status')->map->value->all())
        ->toBe(['assigned', 'in_progress', 'resolved', 'closed']);
});

it('keeps the first response time when work restarts', function () {
    $wo = $this->assign->handle($this->wo, $this->w->technician, $this->w->supervisor);
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);
    $wo = $this->change->handle($wo, WorkOrderStatus::OnHold, $this->w->technician);

    $this->travel(2)->hours();
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);

    expect($wo->responded_at->toDateTimeString())->toBe('2026-05-04 09:00:00');
});

it('adds time on hold to hold_minutes and pushes the resolution due time out', function () {
    $wo = $this->assign->handle($this->wo, $this->w->technician, $this->w->supervisor);
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);

    $wo = $this->change->handle($wo, WorkOrderStatus::OnHold, $this->w->technician, 'Waiting for spare part');
    $this->travel(90)->minutes();
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);

    $wo = $this->change->handle($wo, WorkOrderStatus::OnHold, $this->w->technician);
    $this->travel(30)->minutes();
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);

    expect($wo->hold_minutes)->toBe(120)
        ->and($wo->resolution_due_at->toDateTimeString())->toBe('2026-05-04 19:00:00');
});

it('requires a note to resolve, reopen and cancel', function () {
    expect(errorsFor(fn () => $this->change->handle($this->wo, WorkOrderStatus::Cancelled, $this->w->supervisor)))
        ->toHaveKey('note');

    $wo = $this->assign->handle($this->wo, $this->w->technician, $this->w->supervisor);
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);

    expect(errorsFor(fn () => $this->change->handle($wo, WorkOrderStatus::Resolved, $this->w->technician, '   ')))
        ->toHaveKey('note');

    $wo = $this->change->handle($wo, WorkOrderStatus::Resolved, $this->w->technician, 'Fixed');

    expect(errorsFor(fn () => $this->change->handle($wo, WorkOrderStatus::Assigned, $this->w->clientUser)))
        ->toHaveKey('note');
});

it('reopens a resolved work order back to its technician', function () {
    $wo = $this->assign->handle($this->wo, $this->w->technician, $this->w->supervisor);
    $wo = $this->change->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);
    $wo = $this->change->handle($wo, WorkOrderStatus::Resolved, $this->w->technician, 'Fixed');

    $wo = $this->change->handle($wo, WorkOrderStatus::Assigned, $this->w->clientUser, 'Still leaking');

    expect($wo)->status->toBe(WorkOrderStatus::Assigned)
        ->assigned_to->toBe($this->w->technician->id)
        ->resolved_at->toBeNull()
        ->and($wo->statusHistories()->latest('id')->first()->note)->toBe('Still leaking');
});

it('rejects transitions the state machine does not allow', function (WorkOrderStatus $start, WorkOrderStatus $to) {
    $this->wo->forceFill(['status' => $start, 'assigned_to' => $this->w->technician->id])->save();

    expect(errorsFor(fn () => $this->change->handle($this->wo, $to, $this->w->admin, 'note')))->toHaveKey('status');
    expect($this->wo->fresh()->status)->toBe($start)
        ->and($this->wo->statusHistories()->count())->toBe(0);
})->with([
    'open → resolved' => [WorkOrderStatus::Open, WorkOrderStatus::Resolved],
    'open → in progress' => [WorkOrderStatus::Open, WorkOrderStatus::InProgress],
    'in progress → cancelled' => [WorkOrderStatus::InProgress, WorkOrderStatus::Cancelled],
    'on hold → resolved' => [WorkOrderStatus::OnHold, WorkOrderStatus::Resolved],
    'closed → assigned' => [WorkOrderStatus::Closed, WorkOrderStatus::Assigned],
    'cancelled → open' => [WorkOrderStatus::Cancelled, WorkOrderStatus::Open],
]);

it('does not assign through a bare status change', function () {
    expect(errorsFor(fn () => $this->change->handle($this->wo, WorkOrderStatus::Assigned, $this->w->supervisor)))
        ->toHaveKey('assignee_id');
});

it('only assigns active technicians of the same tenant', function () {
    expect(errorsFor(fn () => $this->assign->handle($this->wo, $this->w->supervisor, $this->w->admin)))->toHaveKey('assignee_id');

    $outsider = userWithRole(UserRole::Technician);
    enterTenant($this->w->tenant);
    expect(errorsFor(fn () => $this->assign->handle($this->wo, $outsider, $this->w->admin)))->toHaveKey('assignee_id');

    $this->w->otherTechnician->update(['is_active' => false]);
    expect(errorsFor(fn () => $this->assign->handle($this->wo, $this->w->otherTechnician, $this->w->admin)))->toHaveKey('assignee_id');
});

it('reassigns to a different technician but not the same one', function () {
    $wo = $this->assign->handle($this->wo, $this->w->technician, $this->w->supervisor);

    expect(errorsFor(fn () => $this->assign->handle($wo, $this->w->technician, $this->w->supervisor)))->toHaveKey('assignee_id');

    $wo = $this->assign->handle($wo, $this->w->otherTechnician, $this->w->supervisor, 'Closer to site');
    expect($wo->assigned_to)->toBe($this->w->otherTechnician->id);
});

it('dispatches status and assignment events', function () {
    Event::fake([WorkOrderStatusChanged::class, WorkOrderAssigned::class]);

    $wo = $this->assign->handle($this->wo, $this->w->technician, $this->w->supervisor);
    $this->assign->handle($wo, $this->w->otherTechnician, $this->w->supervisor);

    Event::assertDispatched(WorkOrderStatusChanged::class, fn ($e) => $e->from === WorkOrderStatus::Open && $e->to === WorkOrderStatus::Assigned);
    Event::assertDispatched(WorkOrderAssigned::class, fn ($e) => $e->assignee->is($this->w->otherTechnician) && $e->previousAssignee?->is($this->w->technician));
    Event::assertDispatchedTimes(WorkOrderAssigned::class, 2);
});

it('does not dispatch anything when a change is rejected', function () {
    Event::fake([WorkOrderStatusChanged::class]);

    rescue(fn () => $this->change->handle($this->wo, WorkOrderStatus::Closed, $this->w->admin), report: false);

    Event::assertNotDispatched(WorkOrderStatusChanged::class);
});
