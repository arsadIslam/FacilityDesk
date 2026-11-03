<x-layouts.guest title="Reset password">
    <x-card class="mx-auto max-w-md p-8">
        <h1 class="text-2xl font-semibold text-slate-900">Forgot your password?</h1>
        <p class="mt-1 text-sm text-slate-600">Enter your email and we'll send you a reset link.</p>

        @if (session('status'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
            @csrf
            <x-form.input name="email" label="Email" type="email" required autofocus />
            <x-button class="w-full">Email reset link</x-button>
        </form>

        <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-500">Back to sign in</a></p>
    </x-card>
</x-layouts.guest>
