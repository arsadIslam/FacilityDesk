<x-layouts.app title="Sites">
    <x-page-header title="Sites" subtitle="Client premises you maintain.">
        @can('create', App\Models\Site::class)
            <x-slot:actions>
                <x-button :href="route('sites.create')">New site</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Client</th>
                    <th class="px-4 py-3">City</th>
                    <th class="px-4 py-3">Supervisor</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sites as $site)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $site->code }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('sites.show', $site) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $site->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $site->client->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $site->city ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $site->supervisor?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><x-status-badge :active="$site->is_active" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No sites yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $sites->links() }}</div>
</x-layouts.app>
