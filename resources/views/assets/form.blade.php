<x-layouts.app :title="$asset->exists ? $asset->code : 'New asset'">
    <x-page-header :title="$asset->exists ? 'Edit '.$asset->code : 'New asset'" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}" class="space-y-5">
            @csrf
            @if ($asset->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-form.input name="name" label="Name" :value="$asset->name" required maxlength="150" placeholder="e.g. Rooftop AC unit 2" />
                </div>
                <x-form.input name="code" label="Code" :value="$asset->code" required maxlength="40" hint="e.g. HVAC-012" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="site_id" label="Site" placeholder="Choose a site" required :value="$asset->site_id" :options="$sites" />
                <div>
                    <label for="location_id" class="block text-sm font-medium text-slate-700">Location</label>
                    <select id="location_id" name="location_id" required
                            class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Choose a location</option>
                        @foreach ($locationsBySite as $locations)
                            <optgroup label="{{ $locations->first()->site->name }}">
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((string) old('location_id', $asset->location_id) === (string) $location->id)>{{ $location->path }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="category_id" label="Category" placeholder="Choose a category" required :value="$asset->category_id" :options="$categories" />
                <x-form.select name="status" label="Status" required :value="$asset->status->value"
                               :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="make" label="Make" :value="$asset->make" maxlength="100" />
                <x-form.input name="model" label="Model" :value="$asset->model" maxlength="100" />
                <x-form.input name="serial_no" label="Serial no." :value="$asset->serial_no" maxlength="100" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="installed_at" label="Installed on" type="date" :value="$asset->installed_at?->format('Y-m-d')" />
                <x-form.input name="warranty_until" label="Warranty until" type="date" :value="$asset->warranty_until?->format('Y-m-d')" />
            </div>

            <div class="flex gap-3">
                <x-button>{{ $asset->exists ? 'Save' : 'Create asset' }}</x-button>
                <x-button variant="secondary" :href="$asset->exists ? route('assets.show', $asset) : route('assets.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
