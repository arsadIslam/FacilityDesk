<x-layouts.app :title="$category->exists ? $category->name : 'New category'">
    <x-page-header :title="$category->exists ? 'Edit '.$category->name : 'New category'" />

    <x-card class="max-w-xl p-6">
        <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="space-y-5">
            @csrf
            @if ($category->exists) @method('PUT') @endif

            <x-form.input name="name" label="Name" :value="$category->name" required maxlength="100" />
            <x-form.select name="default_priority" label="Default priority" required :value="$category->default_priority->value"
                           :options="collect($priorities)->mapWithKeys(fn ($priority) => [$priority->value => $priority->label()])" />
            <x-form.checkbox name="is_active" label="Active" :checked="old('is_active', $category->is_active)" />

            <div class="flex gap-3">
                <x-button>{{ $category->exists ? 'Save' : 'Create category' }}</x-button>
                <x-button variant="secondary" :href="route('categories.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
