@php $tz = app(App\Services\TenantContext::class)->tenant()->timezone; @endphp

<div>
    <x-page-header title="Audit log" subtitle="Changes to work orders, users and roles. Times in {{ $tz }}." />

    <x-card class="mb-4 p-4">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <select wire:model.live="log" aria-label="Area" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Everything</option>
                @foreach ($logs as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="event" aria-label="Event" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Any event</option>
                <option value="created">Created</option>
                <option value="updated">Updated</option>
                <option value="deleted">Deleted</option>
                <option value="role_changed">Role changed</option>
            </select>
            <select wire:model.live="causerId" aria-label="Changed by" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Anyone</option>
                @foreach ($this->people as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
            </select>
            <input type="date" wire:model.live="from" aria-label="From" class="rounded-md border border-slate-300 px-2 py-2 text-sm">
            <input type="date" wire:model.live="to" aria-label="To" class="rounded-md border border-slate-300 px-2 py-2 text-sm">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search…" aria-label="Search" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
    </x-card>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Who</th><th class="px-4 py-3">What</th><th class="px-4 py-3">Changes</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->entries as $entry)
                    @php
                        $old = (array) ($entry->properties['old'] ?? []);
                        $new = (array) ($entry->properties['attributes'] ?? []);
                        $subject = $entry->subject;
                    @endphp
                    <tr wire:key="activity-{{ $entry->id }}" class="align-top">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $entry->created_at->timezone($tz)->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $entry->causer?->name ?? 'System' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs uppercase tracking-wide text-slate-500">{{ $logs[$entry->log_name] ?? $entry->log_name }} · {{ str_replace('_', ' ', $entry->event ?? $entry->description) }}</span>
                            <div class="font-medium text-slate-900">
                                @if ($subject instanceof App\Models\WorkOrder)
                                    <a href="{{ route('work-orders.show', $subject) }}" class="text-indigo-600 hover:text-indigo-500">{{ $subject->number }}</a>
                                @elseif ($subject instanceof App\Models\User)
                                    {{ $subject->name }}
                                @else
                                    <span class="text-slate-400">(deleted)</span>
                                @endif
                            </div>
                            @if ($entry->log_name === 'roles') <div class="text-slate-600">{{ $entry->description }}</div> @endif
                        </td>
                        <td class="px-4 py-3">
                            <dl class="space-y-0.5 text-xs">
                                @foreach ($new as $field => $value)
                                    @php
                                        // Show people by name rather than id.
                                        $label = fn ($v) => $field === 'assigned_to' && $v !== null && isset($this->people[$v]) ? $this->people[$v] : $v;
                                        $value = $label($value);
                                        if (array_key_exists($field, $old)) { $old[$field] = $label($old[$field]); }
                                    @endphp
                                    <div>
                                        <dt class="inline font-medium text-slate-700">{{ $field === 'assigned_to' ? 'assignee' : str_replace('_', ' ', $field) }}:</dt>
                                        <dd class="inline text-slate-600">
                                            @if (array_key_exists($field, $old))
                                                <span class="text-slate-400 line-through">{{ is_scalar($old[$field]) ? \Illuminate\Support\Str::limit((string) $old[$field], 60) : '—' }}</span> →
                                            @endif
                                            {{ is_scalar($value) ? \Illuminate\Support\Str::limit((string) $value, 60) : '—' }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No matching entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $this->entries->links() }}</div>
</div>
