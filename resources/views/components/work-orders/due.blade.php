@props(['workOrder'])

{{-- Resolution deadline, red when breached. Times shown in the site's timezone. --}}
@php
    $due = $workOrder->resolution_due_at;
    $overdue = ! $workOrder->status->isFinished() && $due->isPast();
@endphp

<span @class(['font-medium text-red-700' => $overdue, 'text-slate-600' => ! $overdue])>
    <x-datetime :value="$due" :timezone="$workOrder->site->timezone" />
    @if ($overdue)
        <span class="ml-1 text-xs uppercase">overdue</span>
    @endif
</span>
