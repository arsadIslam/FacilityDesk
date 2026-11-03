<x-layouts.app title="Categories">
    <x-page-header title="Categories" subtitle="Trades a work order can be raised under.">
        @can('create', App\Models\Category::class)
            <x-slot:actions>
                <x-button :href="route('categories.create')">New category</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Default priority</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $category->name }}</td>
                        <td class="px-4 py-3">{{ $category->default_priority->label() }}</td>
                        <td class="px-4 py-3"><x-status-badge :active="$category->is_active" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $category)
                                <a href="{{ route('categories.edit', $category) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
</x-layouts.app>
