<?php

use App\Enums\WorkOrderStatus as S;

/**
 * Every (from, to) pair, so adding a status or a transition forces a
 * conscious update here.
 */
dataset('transitions', function () {
    $allowed = [
        'open' => ['assigned', 'cancelled'],
        'assigned' => ['in_progress', 'assigned', 'cancelled'],
        'in_progress' => ['on_hold', 'resolved'],
        'on_hold' => ['in_progress'],
        'resolved' => ['closed', 'assigned'],
        'closed' => [],
        'cancelled' => [],
    ];

    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            $ok = in_array($to->value, $allowed[$from->value], true);
            yield "{$from->value} → {$to->value} ".($ok ? 'allowed' : 'rejected') => [$from, $to, $ok];
        }
    }
});

it('enforces the lifecycle', function (S $from, S $to, bool $expected) {
    expect($from->canTransitionTo($to))->toBe($expected);
})->with('transitions');

it('treats resolved, closed and cancelled as finished', function () {
    expect(S::finished())->toBe([S::Resolved, S::Closed, S::Cancelled])
        ->and(S::Open->isFinished())->toBeFalse()
        ->and(S::Closed->isFinished())->toBeTrue();
});

it('labels reopen and resume actions', function () {
    expect(S::Resolved->actionLabel(S::Assigned))->toBe('Reopen')
        ->and(S::OnHold->actionLabel(S::InProgress))->toBe('Resume')
        ->and(S::Assigned->actionLabel(S::InProgress))->toBe('Start work');
});
