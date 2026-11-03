<x-layouts.app :title="$user->name">
    <x-page-header :title="'Edit '.$user->name" :subtitle="$user->email" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
            @csrf @method('PUT')
            <x-form.input name="name" label="Full name" :value="$user->name" required maxlength="120" />
            <x-form.input name="phone" label="Phone" :value="$user->phone" maxlength="20" />
            <x-form.select name="role" label="Role" required :value="$user->role()?->value"
                           :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])" />
            <x-form.select name="client_id" label="Client (Client Users only)" placeholder="—" :value="$user->client_id" :options="$clients" />

            <fieldset>
                <legend class="text-sm font-medium text-slate-700">Skills (Technicians only)</legend>
                @php $selectedSkills = collect(old('skill_ids', $user->skills->modelKeys()))->map(fn ($id) => (int) $id); @endphp
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($categories as $id => $name)
                        <x-form.checkbox name="skill_ids[]" :value="$id" :label="$name" :checked="$selectedSkills->contains($id)" />
                    @endforeach
                </div>
                @error('skill_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('skill_ids.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            <div class="flex gap-3">
                <x-button>Save</x-button>
                <x-button variant="secondary" :href="route('users.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
