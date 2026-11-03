<x-layouts.guest title="Accept invitation">
    <x-card class="mx-auto max-w-md p-8">
        <h1 class="text-2xl font-semibold text-slate-900">Welcome, {{ $user->name }}</h1>
        <p class="mt-1 text-sm text-slate-600">Set a password for <strong>{{ $user->email }}</strong> to get started.</p>

        {{-- Posting to the full signed URL keeps the signature for the POST route. --}}
        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6 space-y-5">
            @csrf
            <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
            <x-button class="w-full">Set password</x-button>
        </form>
    </x-card>
</x-layouts.guest>
