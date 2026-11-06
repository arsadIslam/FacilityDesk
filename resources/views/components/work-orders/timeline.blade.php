@props(['workOrder'])

{{-- Status history, oldest first; histories are eager loaded by WorkOrders\Show. --}}
<ol class="relative ml-2 border-l border-slate-200">
    @foreach ($workOrder->statusHistories as $history)
        <li class="mb-5 ml-5" wire:key="history-{{ $history->id }}">
            <span class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-white bg-indigo-500"></span>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @if ($history->from_status)
                    <x-work-orders.status-badge :status="$history->from_status" />
                    <span class="text-slate-400">→</span>
                @endif
                <x-work-orders.status-badge :status="$history->to_status" />
            </div>
            <p class="mt-1 text-xs text-slate-500">
                {{ $history->changedBy?->name ?? 'System' }} ·
                <x-datetime :value="$history->created_at" :timezone="$workOrder->site->timezone" />
            </p>
            @if ($history->note)
                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $history->note }}</p>
            @endif
        </li>
    @endforeach
</ol>
