<div>
    <x-page-header title="Work orders">
        @can('create', App\Models\WorkOrder::class)
            <x-slot:actions>
                <x-button :href="route('work-orders.create')" wire:navigate>Raise work order</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card class="mb-4 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search number or title…" aria-label="Search"
                   class="rounded-md border border-slate-300 px-3 py-2 text-sm lg:col-span-2">

            <select wire:model.live="status" aria-label="Status" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="priority" aria-label="Priority" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">All priorities</option>
                @foreach ($priorities as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="siteId" aria-label="Site" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">All sites</option>
                @foreach ($this->sites as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <select wire:model.live="categoryId" aria-label="Category" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">All categories</option>
                @foreach ($this->categories as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            @if ($canFilterByAssignee)
                <select wire:model.live="assigneeId" aria-label="Assignee" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Any technician</option>
                    @foreach ($this->technicians as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="sort" aria-label="Sort" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Default order</option>
                <option value="newest">Newest first</option>
                <option value="due">Due soonest</option>
            </select>

            <div class="flex items-center gap-2 text-sm">
                <label class="sr-only" for="from">From</label>
                <input id="from" type="date" wire:model.live="from" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                <span class="text-slate-400">–</span>
                <label class="sr-only" for="to">To</label>
                <input id="to" type="date" wire:model.live="to" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model.live="breached" class="h-4 w-4 rounded border-slate-300 text-red-600">
                    SLA breached only
                </label>
                <button type="button" wire:click="clearFilters" class="text-sm font-medium text-slate-600 hover:text-slate-900">Clear</button>
            </div>
        </div>
    </x-card>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm" wire:loading.class="opacity-60">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Number</th>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Site / location</th>
                    <th class="px-4 py-3">Priority</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Assignee</th>
                    <th class="px-4 py-3">Resolve by</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->workOrders as $workOrder)
                    <tr wire:key="wo-{{ $workOrder->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">
                            <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate class="font-semibold text-indigo-600 hover:text-indigo-500">{{ $workOrder->number }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $workOrder->title }}</div>
                            <div class="text-xs text-slate-500">{{ $workOrder->category->name }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $workOrder->site->name }}
                            <div class="text-xs text-slate-500">{{ $workOrder->location->path }}</div>
                        </td>
                        <td class="px-4 py-3"><x-work-orders.priority-badge :priority="$workOrder->priority" /></td>
                        <td class="px-4 py-3"><x-work-orders.status-badge :status="$workOrder->status" /></td>
                        <td class="px-4 py-3 text-slate-600">{{ $workOrder->assignee?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><x-work-orders.due :work-order="$workOrder" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No work orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $this->workOrders->links() }}</div>
</div>
