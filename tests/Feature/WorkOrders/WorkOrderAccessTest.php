<?php

use App\Actions\WorkOrders\AddComment;
use App\Actions\WorkOrders\AssignWorkOrder;
use App\Actions\WorkOrders\ChangeStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->w = workOrderWorld();

    $this->assigned = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['raised_by' => $this->w->supervisor->id]);
    $this->unassigned = WorkOrder::factory()->forSite($this->w->site)->create(['raised_by' => $this->w->supervisor->id]);

    $otherClientSite = Site::factory()->create(['tenant_id' => $this->w->tenant->id]);
    $this->otherClient = WorkOrder::factory()->forSite($otherClientSite)->create(['raised_by' => $this->w->supervisor->id]);

    $this->foreign = WorkOrder::factory()->create();
});

function visibleIds(User $user): array
{
    return WorkOrder::visibleTo($user)->orderBy('id')->pluck('id')->all();
}

it('shows supervisors and admins every work order in the tenant only', function () {
    $expected = [$this->assigned->id, $this->unassigned->id, $this->otherClient->id];

    expect(visibleIds($this->w->supervisor))->toBe($expected)
        ->and(visibleIds($this->w->admin))->toBe($expected);
});

it('shows technicians only their assigned jobs', function () {
    expect(visibleIds($this->w->technician))->toBe([$this->assigned->id])
        ->and(visibleIds($this->w->otherTechnician))->toBe([]);
});

it('shows client users their client\'s work orders and those they raised', function () {
    $raisedElsewhere = WorkOrder::factory()->forSite(Site::factory()->create(['tenant_id' => $this->w->tenant->id]))->create(['raised_by' => $this->w->clientUser->id]);

    expect(visibleIds($this->w->clientUser))->toBe([$this->assigned->id, $this->unassigned->id, $raisedElsewhere->id]);
});

it('lets technicians work only on jobs assigned to them', function () {
    $change = app(ChangeStatus::class);

    $wo = $change->handle($this->assigned, WorkOrderStatus::InProgress, $this->w->technician);
    expect($wo->status)->toBe(WorkOrderStatus::InProgress);

    $this->unassigned->forceFill(['status' => WorkOrderStatus::Assigned, 'assigned_to' => $this->w->otherTechnician->id])->save();

    expect(fn () => $change->handle($this->unassigned, WorkOrderStatus::InProgress, $this->w->technician))
        ->toThrow(AuthorizationException::class);
});

it('does not let technicians cancel or close', function () {
    $change = app(ChangeStatus::class);

    expect(fn () => $change->handle($this->assigned, WorkOrderStatus::Cancelled, $this->w->technician, 'nope'))
        ->toThrow(AuthorizationException::class);
});

it('does not let client users assign or work on jobs', function () {
    $change = app(ChangeStatus::class);

    expect($this->w->clientUser->can('assign', $this->unassigned))->toBeFalse()
        ->and(fn () => app(AssignWorkOrder::class)->handle($this->unassigned, $this->w->technician, $this->w->clientUser))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $change->handle($this->assigned, WorkOrderStatus::InProgress, $this->w->clientUser))
        ->toThrow(AuthorizationException::class);
});

it('lets client users close and reopen their own client\'s resolved work orders only', function () {
    $this->assigned->forceFill(['status' => WorkOrderStatus::Resolved])->save();
    $this->otherClient->forceFill(['status' => WorkOrderStatus::Resolved, 'assigned_to' => $this->w->technician->id])->save();

    expect($this->w->clientUser->can('transition', [$this->assigned, WorkOrderStatus::Closed]))->toBeTrue()
        ->and($this->w->clientUser->can('transition', [$this->assigned, WorkOrderStatus::Assigned]))->toBeTrue()
        ->and($this->w->clientUser->can('transition', [$this->otherClient, WorkOrderStatus::Closed]))->toBeFalse();
});

it('hides internal comments from client users', function () {
    WorkOrderComment::factory()->create(['work_order_id' => $this->assigned->id, 'user_id' => $this->w->technician->id, 'body' => 'Public update']);
    WorkOrderComment::factory()->internal()->create(['work_order_id' => $this->assigned->id, 'user_id' => $this->w->technician->id, 'body' => 'Client is difficult']);

    $bodies = fn ($user) => $this->assigned->comments()->visibleTo($user)->pluck('body')->all();

    expect($bodies($this->w->clientUser))->toBe(['Public update'])
        ->and($bodies($this->w->technician))->toBe(['Public update', 'Client is difficult'])
        ->and($bodies($this->w->supervisor))->toHaveCount(2);
});

it('does not let client users post internal comments', function () {
    expect(fn () => app(AddComment::class)->handle($this->assigned, $this->w->clientUser, 'Sneaky', internal: true))
        ->toThrow(AuthorizationException::class);

    $comment = app(AddComment::class)->handle($this->assigned, $this->w->clientUser, 'Any update?');
    expect($comment->is_internal)->toBeFalse()->and($comment->tenant_id)->toBe($this->w->tenant->id);
});

it('does not let uninvolved users comment', function () {
    expect(fn () => app(AddComment::class)->handle($this->unassigned, $this->w->technician, 'Hi'))
        ->toThrow(AuthorizationException::class);
});

it('returns 404 for another tenant\'s work order on the web', function () {
    $this->actingAs($this->w->admin)
        ->get($this->w->tenant->url("work-orders/{$this->foreign->id}"))
        ->assertNotFound();
});

it('returns 403 for a work order in the tenant the user may not see', function () {
    $this->actingAs($this->w->technician)
        ->get($this->w->tenant->url("work-orders/{$this->unassigned->id}"))
        ->assertForbidden();
});
