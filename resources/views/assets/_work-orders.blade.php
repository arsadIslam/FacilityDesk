{{-- Shared list of an asset's work orders; expects $list (already visibility-scoped). --}}
<ul class="divide-y divide-slate-100">
    @forelse ($list as $workOrder)
        <li class="flex items-center justify-between gap-3 py-3 text-sm">
            <div class="min-w-0">
                <a href="{{ route('work-orders.show', $workOrder) }}" class="font-mono text-xs font-semibold text-indigo-600 hover:text-indigo-500">{{ $workOrder->number }}</a>
                <p class="truncate text-slate-800">{{ $workOrder->title }}</p>
                <p class="text-xs text-slate-500">{{ $workOrder->assignee?->name ?? 'Unassigned' }}</p>
            </div>
            <x-work-orders.status-badge :status="$workOrder->status" />
        </li>
    @empty
        <li class="py-3 text-sm text-slate-500">{{ $empty }}</li>
    @endforelse
</ul>
