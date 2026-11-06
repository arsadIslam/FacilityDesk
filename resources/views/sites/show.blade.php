<x-layouts.app :title="$site->name">
    @php $canManage = auth()->user()->can('update', $site); @endphp

    <x-page-header :title="$site->name" :subtitle="$site->client->name.' · '.$site->code">
        @if ($canManage)
            <x-slot:actions>
                <x-button variant="secondary" :href="route('sites.edit', $site)">Edit site</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="p-6">
                <h2 class="text-base font-semibold text-slate-900">Locations</h2>

                @if ($tree->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">No locations yet. Start with a building.</p>
                @else
                    <div class="mt-3 text-sm">
                        <x-location-tree :site="$site" :tree="$tree" :can-manage="$canManage" />
                    </div>
                @endif

                @if ($canManage)
                    <form method="POST" action="{{ route('sites.locations.store', $site) }}" class="mt-6 grid gap-4 border-t border-slate-200 pt-6 sm:grid-cols-4">
                        @csrf
                        <x-form.select name="parent_id" label="Inside" placeholder="(top level)" :options="$locationOptions" />
                        <x-form.select name="type" label="Type" required
                                       :options="collect($locationTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()])" />
                        <x-form.input name="name" label="Name" required maxlength="120" />
                        <div class="flex items-end"><x-button class="w-full">Add location</x-button></div>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card class="p-6 text-sm">
                <h2 class="text-base font-semibold text-slate-900">Details</h2>
                <dl class="mt-3 space-y-2">
                    <div><dt class="text-slate-500">Address</dt><dd>{{ $site->address ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">City</dt><dd>{{ $site->city ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Timezone</dt><dd>{{ $site->timezone }}</dd></div>
                    <div><dt class="text-slate-500">Supervisor</dt><dd>{{ $site->supervisor?->name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Status</dt><dd><x-status-badge :active="$site->is_active" /></dd></div>
                </dl>
            </x-card>

            <x-card class="p-6 text-sm">
                <h2 class="text-base font-semibold text-slate-900">Coverage</h2>
                <p class="mt-1 text-slate-500">Supervisors and technicians who work this site.</p>

                @if ($canManage)
                    <form method="POST" action="{{ route('sites.coverage', $site) }}" class="mt-4 space-y-2">
                        @csrf @method('PUT')
                        @php $covered = $site->coverage->modelKeys(); @endphp
                        @forelse ($fieldUsers as $user)
                            <div>
                                <x-form.checkbox name="user_ids[]" :value="$user->id" :checked="in_array($user->id, $covered, true)"
                                                 :label="$user->name.' ('.$user->role()?->label().')'" />
                            </div>
                        @empty
                            <p class="text-slate-500">Invite supervisors or technicians first.</p>
                        @endforelse
                        @error('user_ids') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @if ($fieldUsers->isNotEmpty())
                            <x-button class="mt-2">Save coverage</x-button>
                        @endif
                    </form>
                @else
                    <ul class="mt-3 space-y-1">
                        @forelse ($site->coverage as $user)
                            <li>{{ $user->name }}</li>
                        @empty
                            <li class="text-slate-500">Nobody assigned yet.</li>
                        @endforelse
                    </ul>
                @endif
            </x-card>

            @if ($canManage)
                <form method="POST" action="{{ route('sites.destroy', $site) }}" data-confirm="Delete {{ $site->name }}?">
                    @csrf @method('DELETE')
                    <x-button variant="danger" class="w-full">Delete site</x-button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
