<div @if ($this->isRunning()) wire:poll.3s @endif>
    <x-page-header title="Reports" subtitle="Monthly PDF and Excel reports for each client. Generated automatically on the 1st for the previous month." />

    @if ($flash)
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ $flash }}</div>
    @endif

    <x-card class="mb-6 p-4">
        <form wire:submit="generate" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="month" class="block text-sm font-medium text-slate-700">Month</label>
                <select id="month" wire:model="month" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    @foreach ($months as $option)
                        <option value="{{ $option }}">{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $option)->format('F Y') }}</option>
                    @endforeach
                </select>
            </div>
            <x-button wire:loading.attr="disabled">Generate reports</x-button>
            @error('month') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
        </form>
    </x-card>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Month</th><th class="px-4 py-3">Client</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Requested by</th><th class="px-4 py-3">Download</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->reports as $report)
                    <tr wire:key="report-{{ $report->id }}">
                        <td class="px-4 py-3">{{ $report->month->format('M Y') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $report->client->name }}</td>
                        <td class="px-4 py-3">
                            @if ($report->status->isRunning())
                                @php $pct = $this->progress[$report->batch_id] ?? 0; @endphp
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500 transition-all" style="width: {{ $pct }}%"></div></div>
                                    <span class="text-xs text-slate-500">{{ $report->status->label() }} · {{ $pct }}%</span>
                                </div>
                            @elseif ($report->status === App\Enums\ReportStatus::Failed)
                                <span class="text-red-700" title="{{ $report->error }}">Failed</span>
                            @else
                                <span class="text-emerald-700">Ready</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $report->requester?->name ?? 'Scheduled' }}</td>
                        <td class="px-4 py-3">
                            @if ($report->status === App\Enums\ReportStatus::Completed)
                                <a href="{{ $report->downloadUrl('pdf') }}" class="font-medium text-indigo-600 hover:text-indigo-500">PDF</a>
                                <span class="text-slate-300">·</span>
                                <a href="{{ $report->downloadUrl('xlsx') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Excel</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No reports yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>
