<x-layouts.app title="Dashboard">
    <x-page-header :title="'Welcome, '.auth()->user()->name" :subtitle="$tenant->name" />

    <x-card class="p-6">
        <p class="text-sm text-slate-600">
            You are signed in as <strong>{{ auth()->user()->role()?->label() ?? 'a user without a role' }}</strong>.
            Head to <a href="{{ route('work-orders.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Work orders</a>
            to raise or work on jobs. Live dashboard stats arrive in Phase 7.
        </p>
    </x-card>
</x-layouts.app>
