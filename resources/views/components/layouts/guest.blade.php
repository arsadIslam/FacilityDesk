<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <span class="text-lg font-semibold tracking-tight text-slate-900">FacilityDesk</span>
            @isset($nav)
                {{ $nav }}
            @endisset
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-12">
        {{ $slot }}
    </main>
</body>
</html>
