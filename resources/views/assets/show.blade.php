<x-layouts.app :title="$asset->code">
    <x-page-header :title="$asset->name" :subtitle="$asset->code.' · '.$asset->site->name">
        <x-slot:actions>
            @can('update', $asset)
                <x-button variant="secondary" :href="route('assets.edit', $asset)">Edit</x-button>
            @endcan
            @can('create', App\Models\WorkOrder::class)
                <x-button :href="route('work-orders.create', ['asset' => $asset->qr_token])">Raise work order</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="p-6 text-sm">
                <h2 class="text-base font-semibold text-slate-900">Details</h2>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-slate-500">Location</dt><dd>{{ $asset->location->path }}</dd></div>
                    <div><dt class="text-slate-500">Category</dt><dd>{{ $asset->category->name }}</dd></div>
                    <div><dt class="text-slate-500">Make / model</dt><dd>{{ collect([$asset->make, $asset->model])->filter()->implode(' · ') ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Serial no.</dt><dd class="font-mono text-xs">{{ $asset->serial_no ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Installed</dt><dd>{{ $asset->installed_at?->format('d M Y') ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-slate-500">Warranty</dt>
                        <dd>
                            {{ $asset->warranty_until?->format('d M Y') ?? '—' }}
                            @if ($asset->warranty_until)
                                <span @class(['ml-1 text-xs', 'text-emerald-700' => $asset->isUnderWarranty(), 'text-red-700' => ! $asset->isUnderWarranty()])>
                                    {{ $asset->isUnderWarranty() ? 'in warranty' : 'expired' }}
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-slate-500">Status</dt><dd><x-asset-status-badge :status="$asset->status" /></dd></div>
                </dl>
            </x-card>

            <x-card class="p-6">
                <h2 class="text-base font-semibold text-slate-900">Work orders</h2>
                @include('assets._work-orders', ['list' => $workOrders, 'empty' => 'No work orders for this asset yet.'])
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card class="p-6 text-center">
                <h2 class="text-base font-semibold text-slate-900">QR label</h2>
                <div class="mx-auto mt-3 w-48 [&_svg]:h-auto [&_svg]:w-full" aria-label="QR code linking to {{ $asset->scanUrl() }}" role="img">{!! $qrSvg !!}</div>
                <p class="mt-2 font-mono text-xs text-slate-500">{{ $asset->code }}</p>
                <x-button variant="secondary" class="mt-4 w-full" :href="route('assets.label', $asset)">Download label (SVG)</x-button>
                <p class="mt-2 text-xs text-slate-500">Scanning opens this asset on your workspace; staff and the client must be signed in.</p>
            </x-card>

            @can('delete', $asset)
                <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Delete {{ $asset->code }}?')">
                    @csrf @method('DELETE')
                    <x-button variant="danger" class="w-full">Delete asset</x-button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.app>
