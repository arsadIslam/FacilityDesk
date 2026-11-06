<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * The lifecycle state machine. Every status change goes through this.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Assigned, self::Cancelled],
            self::Assigned => [self::InProgress, self::Assigned, self::Cancelled],
            self::InProgress => [self::OnHold, self::Resolved],
            self::OnHold => [self::InProgress],
            self::Resolved => [self::Closed, self::Assigned],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::OnHold => 'On hold',
            default => ucfirst($this->value),
        };
    }

    /**
     * Button text for moving from $this to $to.
     */
    public function actionLabel(self $to): string
    {
        return match (true) {
            $this === self::Resolved && $to === self::Assigned => 'Reopen',
            $this === self::OnHold && $to === self::InProgress => 'Resume',
            $to === self::InProgress => 'Start work',
            $to === self::OnHold => 'Put on hold',
            $to === self::Resolved => 'Mark resolved',
            $to === self::Closed => 'Confirm & close',
            $to === self::Cancelled => 'Cancel',
            default => $to->label(),
        };
    }

    /**
     * Statuses after which the SLA clock no longer runs.
     *
     * @return list<self>
     */
    public static function finished(): array
    {
        return [self::Resolved, self::Closed, self::Cancelled];
    }

    /**
     * @return list<string>
     */
    public static function finishedValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::finished());
    }

    public function isFinished(): bool
    {
        return in_array($this, self::finished(), true);
    }
}
