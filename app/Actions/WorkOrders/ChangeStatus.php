<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderStatusChanged;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\SlaCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The only way a work order changes status.
 *
 * Locks the row, checks the policy and the state machine, applies the
 * lifecycle side effects, writes the history row and fires
 * WorkOrderStatusChanged (after commit).
 */
final class ChangeStatus
{
    public function __construct(private readonly SlaCalculator $sla) {}

    /**
     * @param  array<string, mixed>  $changes  extra attributes set in the same write (used by AssignWorkOrder)
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(WorkOrder $workOrder, WorkOrderStatus $to, User $actor, ?string $note = null, array $changes = []): WorkOrder
    {
        $note = $note === null || trim($note) === '' ? null : trim($note);

        [$locked, $from] = DB::transaction(function () use ($workOrder, $to, $actor, $note, $changes): array {
            // Re-read under a row lock so two people acting at once cannot both
            // apply a transition from the same starting status.
            $locked = WorkOrder::query()->whereKey($workOrder->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            Gate::forUser($actor)->authorize('transition', [$locked, $to]);

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "A work order that is {$from->label()} cannot be moved to {$to->label()}.",
                ]);
            }

            $this->ensureRequirements($locked, $from, $to, $note, $changes);
            $this->applySideEffects($locked, $from, $to, $note);

            $locked->status = $to;
            $locked->forceFill($changes)->save();
            $locked->recordStatus($from, $to, $actor, $note);

            return [$locked, $from];
        });

        WorkOrderStatusChanged::dispatch($locked, $from, $to, $actor);

        return $locked;
    }

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws ValidationException
     */
    private function ensureRequirements(WorkOrder $workOrder, WorkOrderStatus $from, WorkOrderStatus $to, ?string $note, array $changes): void
    {
        $isReopen = $from === WorkOrderStatus::Resolved && $to === WorkOrderStatus::Assigned;

        if ($to === WorkOrderStatus::Assigned && ! $isReopen && ! array_key_exists('assigned_to', $changes)) {
            throw ValidationException::withMessages(['assignee_id' => 'Choose a technician to assign.']);
        }

        $message = match (true) {
            $to === WorkOrderStatus::Resolved => 'Describe how the issue was resolved.',
            $to === WorkOrderStatus::Cancelled => 'Give a reason for cancelling.',
            $isReopen => 'Give a reason for reopening.',
            default => null,
        };

        if ($message !== null && $note === null) {
            throw ValidationException::withMessages(['note' => $message]);
        }
    }

    private function applySideEffects(WorkOrder $workOrder, WorkOrderStatus $from, WorkOrderStatus $to, ?string $note): void
    {
        $now = now();

        if ($from === WorkOrderStatus::OnHold) {
            $heldAt = $workOrder->statusHistories()
                ->where('to_status', WorkOrderStatus::OnHold->value)
                ->latest('id')
                ->value('created_at');

            $minutes = $heldAt === null ? 0 : $this->sla->holdMinutes(CarbonImmutable::parse($heldAt), $now);
            $workOrder->hold_minutes += $minutes;
            $workOrder->resolution_due_at = Carbon::instance($this->sla->extendForHold($workOrder->resolution_due_at, $minutes));
        }

        // First response is the first time anyone starts work; later restarts keep it.
        // A late response is flagged here, since the checker only sees waiting ones.
        if ($to === WorkOrderStatus::InProgress && $workOrder->responded_at === null) {
            $workOrder->responded_at = $now;
            $workOrder->is_response_breached = $workOrder->is_response_breached
                || $this->sla->isResponseBreached($now, $workOrder->response_due_at, $now);
        }

        if ($to === WorkOrderStatus::Resolved) {
            $workOrder->resolved_at = $now;
            $workOrder->resolution_note = $note;
            $workOrder->is_resolution_breached = $workOrder->is_resolution_breached
                || $this->sla->isResolutionBreached($to, $now, $workOrder->resolution_due_at, $now);
        }

        if ($to === WorkOrderStatus::Closed) {
            $workOrder->closed_at = $now;
        }

        // Reopened: the earlier resolution no longer counts.
        if ($from === WorkOrderStatus::Resolved && $to === WorkOrderStatus::Assigned) {
            $workOrder->resolved_at = null;
        }
    }
}
