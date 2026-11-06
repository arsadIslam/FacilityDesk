@props(['status'])

@php
    $classes = match ($status) {
        App\Enums\WorkOrderStatus::Open => 'bg-sky-50 text-sky-700 ring-sky-200',
        App\Enums\WorkOrderStatus::Assigned => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        App\Enums\WorkOrderStatus::InProgress => 'bg-amber-50 text-amber-800 ring-amber-200',
        App\Enums\WorkOrderStatus::OnHold => 'bg-slate-100 text-slate-700 ring-slate-200',
        App\Enums\WorkOrderStatus::Resolved => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        App\Enums\WorkOrderStatus::Closed => 'bg-slate-50 text-slate-500 ring-slate-200',
        App\Enums\WorkOrderStatus::Cancelled => 'bg-red-50 text-red-700 ring-red-200',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset $classes"]) }}>
    {{ $status->label() }}
</span>
