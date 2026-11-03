<x-layouts.guest title="Verify your email">
    <x-card class="mx-auto max-w-md p-8">
        <h1 class="text-2xl font-semibold text-slate-900">Verify your email</h1>
        <p class="mt-2 text-sm text-slate-600">
            We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to finish setting up your account.
        </p>

        @if (session('status'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        <div class="mt-6 flex items-center justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button>Resend link</x-button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-slate-600 hover:text-slate-900">Sign out</button>
            </form>
        </div>
    </x-card>
</x-layouts.guest>
