<x-layouts.guest title="Choose a new password">
    <x-card class="mx-auto max-w-md p-8">
        <h1 class="text-2xl font-semibold text-slate-900">Choose a new password</h1>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-form.input name="email" label="Email" type="email" :value="$email" required autocomplete="username" />
            <x-form.input name="password" label="New password" type="password" required autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
            <x-button class="w-full">Reset password</x-button>
        </form>
    </x-card>
</x-layouts.guest>
