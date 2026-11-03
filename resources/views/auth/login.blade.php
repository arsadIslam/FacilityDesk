<x-layouts.guest title="Sign in">
    <x-slot:nav>
        <span class="text-sm text-slate-600">{{ $tenant->name }}</span>
    </x-slot:nav>

    <x-card class="mx-auto max-w-md p-8">
        <h1 class="text-2xl font-semibold text-slate-900">Sign in to {{ $tenant->name }}</h1>

        @if (session('status'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
            @csrf
            <x-form.input name="email" label="Email" type="email" required autofocus autocomplete="username" />
            <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />

            <div class="flex items-center justify-between">
                <x-form.checkbox name="remember" label="Remember me" />
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Forgot password?</a>
            </div>

            <x-button class="w-full">Sign in</x-button>
        </form>
    </x-card>
</x-layouts.guest>
