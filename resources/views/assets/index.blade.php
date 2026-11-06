<x-layouts.app title="Assets">
    <x-page-header title="Assets" subtitle="Equipment you maintain. Each asset has a QR label for on-site reporting.">
        @can('create', App\Models\Asset::class)
            <x-slot:actions>
                <x-button :href="route('assets.create', array_filter(['site' => $siteId]))">New asset</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <form method="GET" class="mb-4 flex items-end gap-3">
        <div class="w-64">
            <x-form.select name="site" label="Site" placeholder="All sites" :value="$siteId" :options="$sites" data-autosubmit />
        </div>
        <noscript><x-button>Filter</x-button></noscript>
    </form>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Site / location</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Warranty</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($assets as $asset)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('assets.show', $asset) }}" class="font-semibold text-indigo-600 hover:text-indigo-500">{{ $asset->code }}</a>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $asset->name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $asset->site->name }}
                            <div class="text-xs text-slate-500">{{ $asset->location->path }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $asset->category->name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            @if ($asset->warranty_until)
                                <span @class(['text-red-700' => ! $asset->isUnderWarranty()])>{{ $asset->warranty_until->format('d M Y') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-asset-status-badge :status="$asset->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No assets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $assets->links() }}</div>
</x-layouts.app>
