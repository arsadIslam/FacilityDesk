<x-layouts.app :title="$asset->code">
    {{-- Phone-first: this is what opens when someone scans the label on site. --}}
    <div class="mx-auto max-w-md space-y-4">
        <x-card class="p-5">
            <p class="font-mono text-xs text-slate-500">{{ $asset->code }}</p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $asset->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $asset->site->name }} · {{ $asset->location->path }}</p>
            <div class="mt-3 flex items-center gap-2">
                <x-asset-status-badge :status="$asset->status" />
                <span class="text-xs text-slate-500">{{ $asset->category->name }}</span>
            </div>

            @if ($canRaise)
                <x-button class="mt-5 w-full py-3 text-base" :href="route('work-orders.create', ['asset' => $asset->qr_token])">Report a problem</x-button>
            @endif
        </x-card>

        <x-card class="p-5">
            <h2 class="text-sm font-semibold text-slate-900">Open work orders</h2>
            <p class="text-xs text-slate-500">Check here first so the same problem isn't reported twice.</p>
            @include('assets._work-orders', ['list' => $openWorkOrders, 'empty' => 'Nothing open for this asset.'])
        </x-card>
    </div>
</x-layouts.app>
