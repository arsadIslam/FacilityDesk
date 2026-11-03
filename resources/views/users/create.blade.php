<x-layouts.app title="Invite user">
    <x-page-header title="Invite user" subtitle="They'll get an email with a link to set their password." />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
            @csrf
            <x-form.input name="name" label="Full name" required maxlength="120" />
            <x-form.input name="email" label="Email" type="email" required maxlength="150" />
            <x-form.input name="phone" label="Phone" maxlength="20" />
            <x-form.select name="role" label="Role" placeholder="Choose a role" required
                           :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])" />
            <x-form.select name="client_id" label="Client (Client Users only)" placeholder="—" :options="$clients" />

            <div class="flex gap-3">
                <x-button>Send invitation</x-button>
                <x-button variant="secondary" :href="route('users.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
