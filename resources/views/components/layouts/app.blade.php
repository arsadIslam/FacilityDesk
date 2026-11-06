@props(['title' => null])

@php
    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'can' => null],
        ['label' => 'Work orders', 'route' => 'work-orders.index', 'active' => 'work-orders.*', 'can' => ['viewAny', App\Models\WorkOrder::class]],
        ['label' => 'Clients', 'route' => 'clients.index', 'active' => 'clients.*', 'can' => ['viewAny', App\Models\Client::class]],
        ['label' => 'Sites', 'route' => 'sites.index', 'active' => 'sites.*', 'can' => ['viewAny', App\Models\Site::class]],
        ['label' => 'Assets', 'route' => 'assets.index', 'active' => 'assets.*', 'can' => ['viewAny', App\Models\Asset::class]],
        ['label' => 'Categories', 'route' => 'categories.index', 'active' => 'categories.*', 'can' => ['viewAny', App\Models\Category::class]],
        ['label' => 'SLA policies', 'route' => 'sla-policies.index', 'active' => 'sla-policies.*', 'can' => ['viewAny', App\Models\SlaPolicy::class]],
        ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'can' => ['viewAny', App\Models\User::class]],
        ['label' => 'Reports', 'route' => 'reports.index', 'active' => 'reports.*', 'can' => ['viewAny', App\Models\Report::class]],
        ['label' => 'Audit', 'route' => 'audit.index', 'active' => 'audit.*', 'permission' => App\Enums\Permission::ViewAuditLog],
    ];
    $tenant = app(App\Services\TenantContext::class)->tenant();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ $tenant?->name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4">
            <div class="flex items-center justify-between gap-4 py-3">
                <a href="{{ route('dashboard') }}" class="text-base font-semibold tracking-tight text-slate-900">
                    {{ $tenant?->name }}
                </a>
                <div class="flex items-center gap-3 text-sm">
                    <livewire:notification-bell />
                    <span class="hidden text-slate-600 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="font-medium text-slate-600 hover:text-slate-900">Sign out</button>
                    </form>
                </div>
            </div>
            <nav class="-mb-px flex gap-1 overflow-x-auto text-sm" aria-label="Main">
                @foreach ($nav as $item)
                    @if (isset($item['permission']) ? auth()->user()->hasPermission($item['permission']) : ($item['can'] === null || auth()->user()->can(...$item['can'])))
                        <a href="{{ route($item['route']) }}"
                           @if (request()->routeIs($item['active'])) aria-current="page" @endif
                           @class([
                               'whitespace-nowrap border-b-2 px-3 py-2 font-medium',
                               'border-indigo-600 text-slate-900' => request()->routeIs($item['active']),
                               'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' => ! request()->routeIs($item['active']),
                           ])>{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        <x-flash />
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
