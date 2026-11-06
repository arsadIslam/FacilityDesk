<div>
    <x-page-header :title="'Welcome, '.auth()->user()->name" :subtitle="$tenant->name.' · '.(auth()->user()->role()?->label() ?? '')" />

    @if ($isManager)
        @php $s = $this->stats; @endphp

        <x-card class="mb-6 p-4">
            <div class="grid gap-3 sm:grid-cols-4">
                <select wire:model.live="siteId" aria-label="Site" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All sites</option>
                    @foreach ($this->sites as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm text-slate-600">From
                    <input type="date" wire:model.live="from" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600">To
                    <input type="date" wire:model.live="to" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                </label>
                <p class="self-center text-right text-xs text-slate-400">Updated {{ \Illuminate\Support\Carbon::parse($s['generated_at'])->diffForHumans() }}</p>
            </div>
        </x-card>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" wire:loading.class="opacity-60">
            @foreach ([
                ['Open now', $s['now']['open'], route('work-orders.index'), null],
                ['Breached (open)', $s['now']['breached'], route('work-orders.index', ['breached' => 1]), $s['now']['breached'] > 0 ? 'text-red-700' : null],
                ['Raised in period', $s['period']['created'], null, null],
                ['Closed in period', $s['period']['closed'], null, null],
            ] as [$label, $value, $href, $tone])
                <x-card class="p-5">
                    <p class="text-sm text-slate-500">{{ $label }}</p>
                    <p @class(['mt-1 text-3xl font-semibold text-slate-900', $tone])>{{ number_format($value) }}</p>
                    @if ($href) <a href="{{ $href }}" class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-500">View →</a> @endif
                </x-card>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ([['Resolution SLA met', $s['period']['resolution_compliance'], $s['period']['resolved'].' resolved'], ['Response SLA met', $s['period']['response_compliance'], null]] as [$label, $percent, $note])
                <x-card class="p-5">
                    <div class="flex items-baseline justify-between">
                        <p class="text-sm text-slate-500">{{ $label }}</p>
                        @if ($note) <p class="text-xs text-slate-400">{{ $note }}</p> @endif
                    </div>
                    <p class="mt-1 text-3xl font-semibold text-slate-900">{{ $percent === null ? '—' : $percent.'%' }}</p>
                    <div class="mt-3 h-2 rounded-full bg-slate-100" role="img" aria-label="{{ $label }}: {{ $percent ?? 'no data' }}">
                        <div @class(['h-2 rounded-full', 'bg-emerald-500' => ($percent ?? 0) >= 90, 'bg-amber-500' => ($percent ?? 0) >= 75 && ($percent ?? 0) < 90, 'bg-red-500' => ($percent ?? 0) < 75])
                             style="width: {{ $percent ?? 0 }}%"></div>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-card class="overflow-x-auto">
                <h2 class="border-b border-slate-100 px-5 py-3 text-base font-semibold text-slate-900">Jobs per technician</h2>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-2">Technician</th><th class="px-5 py-2 text-right">Open now</th><th class="px-5 py-2 text-right">Resolved</th><th class="px-5 py-2 text-right">Late</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($s['technicians'] as $technician)
                            <tr>
                                <td class="px-5 py-2">
                                    <a href="{{ route('work-orders.index', ['assignee' => $technician['id']]) }}" class="text-slate-900 hover:text-indigo-600">{{ $technician['name'] }}</a>
                                </td>
                                <td class="px-5 py-2 text-right">{{ $technician['open'] }}</td>
                                <td class="px-5 py-2 text-right">{{ $technician['resolved'] }}</td>
                                <td @class(['px-5 py-2 text-right', 'text-red-700' => $technician['breached'] > 0])>{{ $technician['breached'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">No assigned work yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            <x-card class="p-5">
                <h2 class="text-base font-semibold text-slate-900">Top categories <span class="text-sm font-normal text-slate-500">(raised in period)</span></h2>
                @php $max = max(1, collect($s['top_categories'])->max('total') ?? 1); @endphp
                <ul class="mt-4 space-y-3">
                    @forelse ($s['top_categories'] as $category)
                        <li>
                            <div class="flex justify-between text-sm"><span>{{ $category['name'] }}</span><span class="text-slate-500">{{ $category['total'] }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width: {{ round($category['total'] / $max * 100) }}%"></div></div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Nothing raised in this period.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    @else
        @php $m = $this->mine; @endphp
        <div class="grid gap-4 sm:grid-cols-3">
            <x-card class="p-5"><p class="text-sm text-slate-500">{{ $isClient ? 'Your open requests' : 'Your open jobs' }}</p><p class="mt-1 text-3xl font-semibold">{{ $m['open'] }}</p></x-card>
            <x-card class="p-5"><p class="text-sm text-slate-500">Past SLA</p><p @class(['mt-1 text-3xl font-semibold', 'text-red-700' => $m['overdue'] > 0])>{{ $m['overdue'] }}</p></x-card>
            @if ($isClient)
                <x-card class="p-5"><p class="text-sm text-slate-500">Waiting for your confirmation</p><p class="mt-1 text-3xl font-semibold">{{ $m['awaiting_confirmation'] }}</p></x-card>
            @else
                <x-card class="p-5"><p class="text-sm text-slate-500">Due today</p><p class="mt-1 text-3xl font-semibold">{{ $m['due_today'] }}</p></x-card>
            @endif
        </div>
        <div class="mt-6">
            <x-button :href="route('work-orders.index')">{{ $isClient ? 'View your requests' : 'View your jobs' }}</x-button>
        </div>
    @endif
</div>
