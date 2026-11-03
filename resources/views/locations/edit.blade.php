<x-layouts.app :title="$location->name">
    <x-page-header :title="'Edit '.$location->name" :subtitle="$site->name" />

    <x-card class="max-w-xl p-6">
        <form method="POST" action="{{ route('sites.locations.update', [$site, $location]) }}" class="space-y-5">
            @csrf @method('PUT')
            <x-form.select name="parent_id" label="Inside" placeholder="(top level)" :value="$location->parent_id" :options="$parentOptions" />
            <x-form.select name="type" label="Type" required :value="$location->type->value"
                           :options="collect($locationTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()])" />
            <x-form.input name="name" label="Name" :value="$location->name" required maxlength="120" />

            <div class="flex gap-3">
                <x-button>Save</x-button>
                <x-button variant="secondary" :href="route('sites.show', $site)">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
