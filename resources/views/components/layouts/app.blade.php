@props(['title' => null])

@php
    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'can' => null],
        ['label' => 'Clients', 'route' => 'clients.index', 'active' => 'clients.*', 'can' => ['viewAny', App\Models\Client::class]],
        ['label' => 'Sites', 'route' => 'sites.index', 'active' => 'sites.*', 'can' => ['viewAny', App\Models\Site::class]],
        ['label' => 'Categories', 'route' => 'categories.index', 'active' => 'categories.*', 'can' => ['viewAny', App\Models\Category::class]],
        ['label' => 'SLA policies', 'route' => 'sla-policies.index', 'active' => 'sla-policies.*', 'can' => ['viewAny', App\Models\SlaPolicy::class]],
        ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'can' => ['viewAny', App\Models\User::class]],
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
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="text-base font-semibold tracking-tight text-slate-900">
                    {{ $tenant?->name }}
                </a>
                <nav class="flex flex-wrap gap-1 text-sm">
                    @foreach ($nav as $item)
                        @if ($item['can'] === null || auth()->user()->can(...$item['can']))
                            <a href="{{ route($item['route']) }}"
                               @class([
                                   'rounded-md px-3 py-1.5 font-medium',
                                   'bg-slate-100 text-slate-900' => request()->routeIs($item['active']),
                                   'text-slate-600 hover:text-slate-900' => ! request()->routeIs($item['active']),
                               ])>{{ $item['label'] }}</a>
                        @endif
                    @endforeach
                </nav>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-slate-600">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="font-medium text-slate-600 hover:text-slate-900">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        <x-flash />
        {{ $slot }}
    </main>
</body>
</html>
