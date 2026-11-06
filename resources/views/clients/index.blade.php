<x-layouts.app title="Clients">
    <x-page-header title="Clients" subtitle="The companies whose sites you maintain.">
        @can('create', App\Models\Client::class)
            <x-slot:actions>
                <x-button :href="route('clients.create')">New client</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Contact</th>
                    <th class="px-4 py-3">GSTIN</th>
                    <th class="px-4 py-3">Sites</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($clients as $client)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $client->name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $client->contact_person ?? '—' }}
                            @if ($client->email) <div class="text-xs text-slate-500">{{ $client->email }}</div> @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $client->gstin ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $client->sites_count }}</td>
                        <td class="px-4 py-3"><x-status-badge :active="$client->is_active" /></td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3">
                                @can('update', $client)
                                    <a href="{{ route('clients.edit', $client) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                                @endcan
                                @can('delete', $client)
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" data-confirm="Delete {{ $client->name }}?">
                                        @csrf @method('DELETE')
                                        <button class="font-medium text-red-600 hover:text-red-500">Delete</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No clients yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $clients->links() }}</div>
</x-layouts.app>
