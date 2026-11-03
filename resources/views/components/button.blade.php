@props(['variant' => 'primary', 'href' => null])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-500',
        'link' => 'text-indigo-600 hover:text-indigo-500 px-0 py-0 shadow-none',
        default => 'bg-indigo-600 text-white hover:bg-indigo-500',
    };
    $classes = 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold shadow-sm '.$classes;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
