<x-layouts.app title="Dashboard">
    <x-page-header :title="'Welcome, '.auth()->user()->name" :subtitle="$tenant->name" />

    <x-card class="p-6">
        <p class="text-sm text-slate-600">
            You are signed in as <strong>{{ auth()->user()->role()?->label() ?? 'a user without a role' }}</strong>.
            Work orders arrive in the next phase; the dashboard with live stats in Phase 7.
        </p>
    </x-card>
</x-layouts.app>
