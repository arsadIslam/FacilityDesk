@props(['status'])

@php
    $classes = match ($status) {
        App\Enums\AssetStatus::Working => 'bg-emerald-50 text-emerald-700',
        App\Enums\AssetStatus::UnderRepair => 'bg-amber-50 text-amber-800',
        App\Enums\AssetStatus::Retired => 'bg-slate-100 text-slate-500',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2 py-0.5 text-xs font-medium $classes"]) }}>{{ $status->label() }}</span>
