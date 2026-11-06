@props(['value', 'timezone' => 'UTC', 'format' => 'd M Y, H:i'])

{{-- Stored in UTC, shown in the site's timezone. --}}
@if ($value)
    <time datetime="{{ $value->toIso8601String() }}" title="{{ $value->copy()->timezone($timezone)->format('D d M Y H:i T') }}">
        {{ $value->copy()->timezone($timezone)->format($format) }}
    </time>
@else
    <span class="text-slate-400">—</span>
@endif
