<?php

use App\Enums\Priority;
use App\Enums\WorkOrderStatus;
use App\Services\SlaCalculator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->sla = new SlaCalculator;
    $this->raised = CarbonImmutable::parse('2026-03-10 09:00:00', 'UTC');
});

it('computes due times for each default priority', function (Priority $priority, string $response, string $resolution) {
    $targets = $priority->defaultSla();
    $due = $this->sla->dueDates($this->raised, $targets['response'], $targets['resolution']);

    expect($due['response_due_at']->toDateTimeString())->toBe($response)
        ->and($due['resolution_due_at']->toDateTimeString())->toBe($resolution);
})->with([
    'critical: 15 min / 4 h' => [Priority::Critical, '2026-03-10 09:15:00', '2026-03-10 13:00:00'],
    'high: 1 h / 8 h' => [Priority::High, '2026-03-10 10:00:00', '2026-03-10 17:00:00'],
    'medium: 4 h / 1 day' => [Priority::Medium, '2026-03-10 13:00:00', '2026-03-11 09:00:00'],
    'low: 1 day / 3 days' => [Priority::Low, '2026-03-11 09:00:00', '2026-03-13 09:00:00'],
]);

it('does not mutate the raised-at time it is given', function () {
    $raised = Carbon\Carbon::parse('2026-03-10 09:00:00');

    $this->sla->dueDates($raised, 15, 240);

    expect($raised->toDateTimeString())->toBe('2026-03-10 09:00:00');
});

it('counts whole minutes on hold and never negative', function () {
    expect($this->sla->holdMinutes($this->raised, $this->raised->addMinutes(90)->addSeconds(59)))->toBe(90)
        ->and($this->sla->holdMinutes($this->raised, $this->raised->subMinute()))->toBe(0);
});

it('extends the resolution due time by the time on hold', function () {
    $due = $this->raised->addHours(4);

    expect($this->sla->extendForHold($due, 45)->toDateTimeString())->toBe('2026-03-10 13:45:00')
        ->and($this->sla->extendForHold($due, 0)->equalTo($due))->toBeTrue();
});

it('detects response breaches', function (?string $respondedAt, string $now, bool $breached) {
    $due = $this->raised->addMinutes(15);

    expect($this->sla->isResponseBreached(
        $respondedAt ? CarbonImmutable::parse($respondedAt) : null,
        $due,
        CarbonImmutable::parse($now),
    ))->toBe($breached);
})->with([
    'waiting, before due' => [null, '2026-03-10 09:10:00', false],
    'waiting, past due' => [null, '2026-03-10 09:16:00', true],
    'responded in time' => ['2026-03-10 09:14:00', '2026-03-10 12:00:00', false],
    'responded late' => ['2026-03-10 09:20:00', '2026-03-10 12:00:00', true],
]);

it('detects resolution breaches', function (WorkOrderStatus $status, ?string $resolvedAt, string $now, bool $breached) {
    $due = $this->raised->addHours(4);

    expect($this->sla->isResolutionBreached(
        $status,
        $resolvedAt ? CarbonImmutable::parse($resolvedAt) : null,
        $due,
        CarbonImmutable::parse($now),
    ))->toBe($breached);
})->with([
    'open, before due' => [WorkOrderStatus::InProgress, null, '2026-03-10 12:00:00', false],
    'open, past due' => [WorkOrderStatus::InProgress, null, '2026-03-10 13:01:00', true],
    'on hold: clock paused' => [WorkOrderStatus::OnHold, null, '2026-03-10 14:00:00', false],
    'resolved in time, checked later' => [WorkOrderStatus::Resolved, '2026-03-10 12:59:00', '2026-03-11 09:00:00', false],
    'resolved late' => [WorkOrderStatus::Closed, '2026-03-10 13:30:00', '2026-03-11 09:00:00', true],
    'cancelled never breaches' => [WorkOrderStatus::Cancelled, null, '2026-03-12 09:00:00', false],
]);
