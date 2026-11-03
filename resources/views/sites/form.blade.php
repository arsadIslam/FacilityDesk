<x-layouts.app :title="$site->exists ? $site->name : 'New site'">
    <x-page-header :title="$site->exists ? 'Edit '.$site->name : 'New site'" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $site->exists ? route('sites.update', $site) : route('sites.store') }}" class="space-y-5">
            @csrf
            @if ($site->exists) @method('PUT') @endif

            <x-form.select name="client_id" label="Client" placeholder="Choose a client" required :value="$site->client_id" :options="$clients" />
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-form.input name="name" label="Name" :value="$site->name" required maxlength="150" />
                </div>
                <x-form.input name="code" label="Code" :value="$site->code" required maxlength="20" hint="e.g. BLR-01" />
            </div>
            <div>
                <label for="address" class="block text-sm font-medium text-slate-700">Address</label>
                <textarea id="address" name="address" rows="2" maxlength="1000"
                          class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">{{ old('address', $site->address) }}</textarea>
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="city" label="City" :value="$site->city" maxlength="100" />
                <x-form.select name="timezone" label="Timezone" required :value="$site->timezone"
                               :options="array_combine($timezones, $timezones)" />
            </div>
            <x-form.select name="supervisor_id" label="Site supervisor" placeholder="—" :value="$site->supervisor_id" :options="$supervisors" />
            <x-form.checkbox name="is_active" label="Active" :checked="old('is_active', $site->is_active)" />

            <div class="flex gap-3">
                <x-button>{{ $site->exists ? 'Save' : 'Create site' }}</x-button>
                <x-button variant="secondary" :href="$site->exists ? route('sites.show', $site) : route('sites.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
