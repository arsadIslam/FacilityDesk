@props(['priority'])

@php
    $classes = match ($priority) {
        App\Enums\Priority::Critical => 'bg-red-600 text-white',
        App\Enums\Priority::High => 'bg-orange-100 text-orange-800',
        App\Enums\Priority::Medium => 'bg-yellow-50 text-yellow-800',
        App\Enums\Priority::Low => 'bg-slate-100 text-slate-600',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold $classes"]) }}>
    {{ $priority->label() }}
</span>
