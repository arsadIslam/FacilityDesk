<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkOrderStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pure SLA arithmetic: no database, no clock of its own (callers pass "now").
 *
 * Targets are calendar minutes (24x7); business-hours calendars are out of
 * scope for v1.
 */
final class SlaCalculator
{
    /**
     * @return array{response_due_at: CarbonImmutable, resolution_due_at: CarbonImmutable}
     */
    public function dueDates(CarbonInterface $raisedAt, int $responseMinutes, int $resolutionMinutes): array
    {
        $raisedAt = CarbonImmutable::instance($raisedAt);

        return [
            'response_due_at' => $raisedAt->addMinutes($responseMinutes),
            'resolution_due_at' => $raisedAt->addMinutes($resolutionMinutes),
        ];
    }

    /**
     * Whole minutes spent on hold, rounded down; never negative.
     */
    public function holdMinutes(CarbonInterface $heldAt, CarbonInterface $resumedAt): int
    {
        return max(0, (int) floor($heldAt->diffInSeconds($resumedAt, false) / 60));
    }

    /**
     * Time on hold is not the team's fault, so it pushes the resolution target out.
     */
    public function extendForHold(CarbonInterface $resolutionDueAt, int $holdMinutes): CarbonImmutable
    {
        return CarbonImmutable::instance($resolutionDueAt)->addMinutes($holdMinutes);
    }

    /**
     * Breached if not responded to in time: still waiting past the due time,
     * or responded late.
     */
    public function isResponseBreached(?CarbonInterface $respondedAt, CarbonInterface $responseDueAt, CarbonInterface $now): bool
    {
        return ($respondedAt ?? $now)->greaterThan($responseDueAt);
    }

    /**
     * Breached if not resolved in time: still open past the due time, or
     * resolved late. Cancelled work orders never breach.
     *
     * On hold the clock is paused: the due time is only pushed out when work
     * resumes (ChangeStatus), so judging it now would flag time that will not
     * count. It is judged again once it is back in progress.
     */
    public function isResolutionBreached(
        WorkOrderStatus $status,
        ?CarbonInterface $resolvedAt,
        CarbonInterface $resolutionDueAt,
        CarbonInterface $now,
    ): bool {
        if ($status === WorkOrderStatus::Cancelled || $status === WorkOrderStatus::OnHold) {
            return false;
        }

        $finishedAt = $status->isFinished() ? ($resolvedAt ?? $now) : $now;

        return $finishedAt->greaterThan($resolutionDueAt);
    }
}
