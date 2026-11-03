@props(['active'])

@if ($active)
    <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Active</span>
@else
    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactive</span>
@endif
