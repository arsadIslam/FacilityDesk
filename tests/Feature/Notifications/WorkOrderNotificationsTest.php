<?php

use App\Actions\WorkOrders\AssignWorkOrder;
use App\Actions\WorkOrders\ChangeStatus;
use App\Actions\WorkOrders\CreateWorkOrder;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderCreated;
use App\Events\WorkOrderStatusChanged;
use App\Listeners\NotifyRaiserOfResolution;
use App\Listeners\NotifySupervisorsOfNewWorkOrder;
use App\Listeners\SendAssignmentNotification;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderAssigned as AssignedNotification;
use App\Notifications\WorkOrderRaised;
use App\Notifications\WorkOrderResolved;
use App\Services\TenantContext;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->w = workOrderWorld();
});

function raise(stdClass $w, User $by): WorkOrder
{
    return app(CreateWorkOrder::class)->handle($by, $w->site, $w->location, $w->category, 'Leaking pipe');
}

it('wires each event to its queued listener', function () {
    Event::fake();

    Event::assertListening(WorkOrderCreated::class, NotifySupervisorsOfNewWorkOrder::class);
    Event::assertListening(WorkOrderAssigned::class, SendAssignmentNotification::class);
    Event::assertListening(WorkOrderStatusChanged::class, NotifyRaiserOfResolution::class);
});

it('queues listeners on the queues from the spec', function () {
    Queue::fake();

    $wo = raise($this->w, $this->w->clientUser);
    app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);

    Queue::assertPushedOn('default', CallQueuedListener::class, fn ($job) => $job->class === NotifySupervisorsOfNewWorkOrder::class);
    Queue::assertPushedOn('notifications', CallQueuedListener::class, fn ($job) => $job->class === SendAssignmentNotification::class);
});

it('does not queue the resolution listener for other status changes', function () {
    Queue::fake();

    $wo = raise($this->w, $this->w->clientUser);
    app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);

    Queue::assertNotPushed(CallQueuedListener::class, fn ($job) => $job->class === NotifyRaiserOfResolution::class);
});

it('notifies the technician by mail and in the app when assigned', function () {
    Notification::fake();
    $wo = raise($this->w, $this->w->clientUser);

    app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);

    Notification::assertSentTo($this->w->technician, AssignedNotification::class, function (AssignedNotification $n, array $channels) use ($wo) {
        $mail = $n->toMail($this->w->technician);

        return $channels === ['mail', 'database']
            && $n->workOrder->is($wo)
            && $mail->actionUrl === $this->w->tenant->url("work-orders/{$wo->id}")
            && str_contains($mail->subject, $wo->number);
    });
    Notification::assertNotSentTo($this->w->otherTechnician, AssignedNotification::class);
});

it('notifies the site supervisor of a new work order, not the raiser', function () {
    Notification::fake();
    $this->w->site->update(['supervisor_id' => $this->w->supervisor->id]);
    $otherSupervisor = userWithRole(UserRole::Supervisor, $this->w->tenant);
    enterTenant($this->w->tenant);

    raise($this->w, $this->w->clientUser);

    Notification::assertSentTo($this->w->supervisor, WorkOrderRaised::class);
    Notification::assertNotSentTo($otherSupervisor, WorkOrderRaised::class);
    Notification::assertNotSentTo($this->w->clientUser, WorkOrderRaised::class);
});

it('falls back to every supervisor when the site has none', function () {
    Notification::fake();
    $second = userWithRole(UserRole::Supervisor, $this->w->tenant);
    $outsider = userWithRole(UserRole::Supervisor);
    enterTenant($this->w->tenant);

    raise($this->w, $this->w->clientUser);

    Notification::assertSentTo([$this->w->supervisor, $second], WorkOrderRaised::class);
    Notification::assertNotSentTo($outsider, WorkOrderRaised::class);
});

it('tells the raiser when their work order is resolved', function () {
    Notification::fake();
    $wo = raise($this->w, $this->w->clientUser);
    $wo = app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);
    $wo = app(ChangeStatus::class)->handle($wo, WorkOrderStatus::InProgress, $this->w->technician);

    Notification::assertNotSentTo($this->w->clientUser, WorkOrderResolved::class);

    app(ChangeStatus::class)->handle($wo, WorkOrderStatus::Resolved, $this->w->technician, 'Fixed');

    Notification::assertSentTo($this->w->clientUser, WorkOrderResolved::class);
});

it('does not tell raisers about resolutions they made themselves', function () {
    Notification::fake();
    $wo = raise($this->w, $this->w->supervisor);
    $wo = app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);
    $wo->forceFill(['status' => WorkOrderStatus::InProgress])->save();

    app(ChangeStatus::class)->handle($wo, WorkOrderStatus::Resolved, $this->w->supervisor, 'Done');

    Notification::assertNotSentTo($this->w->supervisor, WorkOrderResolved::class);
});

it('stores in-app notifications with the tenant id and a relative path', function () {
    $wo = raise($this->w, $this->w->clientUser);
    app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);

    $row = DB::table('notifications')->where('notifiable_id', $this->w->technician->id)->where('type', AssignedNotification::class)->sole();

    expect($row->tenant_id)->toBe($this->w->tenant->id)
        ->and(json_decode($row->data, true))->toMatchArray([
            'work_order_id' => $wo->id,
            'number' => $wo->number,
            'path' => "/work-orders/{$wo->id}",
        ]);
});

it('runs queued listeners inside the right tenant even from a context-free worker', function () {
    Notification::fake();
    $wo = raise($this->w, $this->w->clientUser);
    $event = new WorkOrderAssigned($wo, $this->w->technician, null, $this->w->supervisor);

    // A worker has no tenant in context.
    app(TenantContext::class)->clear();

    $listener = new SendAssignmentNotification;
    $tenantSeen = null;
    $listener->middleware($event)[0]->handle($listener, function ($l) use ($event, &$tenantSeen) {
        $tenantSeen = app(TenantContext::class)->id();
        $l->handle($event);
    });

    expect($tenantSeen)->toBe($this->w->tenant->id);
    Notification::assertSentTo($this->w->technician, AssignedNotification::class);
});
