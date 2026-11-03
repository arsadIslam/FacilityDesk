<x-layouts.app :title="$client->exists ? $client->name : 'New client'">
    <x-page-header :title="$client->exists ? 'Edit '.$client->name : 'New client'" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="space-y-5">
            @csrf
            @if ($client->exists) @method('PUT') @endif

            <x-form.input name="name" label="Name" :value="$client->name" required maxlength="150" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="contact_person" label="Contact person" :value="$client->contact_person" maxlength="120" />
                <x-form.input name="phone" label="Phone" :value="$client->phone" maxlength="20" />
            </div>
            <x-form.input name="email" label="Email" type="email" :value="$client->email" maxlength="150" />
            <x-form.input name="gstin" label="GSTIN" :value="$client->gstin" maxlength="15" hint="15 characters, e.g. 27AAPFU0939F1ZV" />
            <x-form.checkbox name="is_active" label="Active" :checked="old('is_active', $client->exists ? $client->is_active : true)" />

            <div class="flex gap-3">
                <x-button>{{ $client->exists ? 'Save' : 'Create client' }}</x-button>
                <x-button variant="secondary" :href="route('clients.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
