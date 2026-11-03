<x-layouts.guest title="Create your workspace">
    <div class="mx-auto max-w-lg rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-900">Create your workspace</h1>
        <p class="mt-1 text-sm text-slate-600">Start a free trial. No card required.</p>

        <form method="POST" action="{{ route('signup.store') }}" class="mt-8 space-y-5" novalidate>
            @csrf

            <fieldset class="space-y-5">
                <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Company</legend>

                <div>
                    <label for="company_name" class="block text-sm font-medium text-slate-700">Company name</label>
                    <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required maxlength="150"
                           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700">Workspace URL</label>
                    <div class="mt-1 flex rounded-md border border-slate-300 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                        <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required maxlength="60"
                               class="block w-full min-w-0 rounded-l-md px-3 py-2 text-sm focus:outline-none" placeholder="acme">
                        <span class="flex items-center rounded-r-md bg-slate-50 px-3 text-sm text-slate-500">.{{ $domain }}</span>
                    </div>
                    @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <fieldset class="space-y-5 border-t border-slate-200 pt-5">
                <legend class="pt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Your admin account</legend>

                <div>
                    <label for="admin_name" class="block text-sm font-medium text-slate-700">Full name</label>
                    <input id="admin_name" name="admin_name" type="text" value="{{ old('admin_name') }}" required maxlength="120" autocomplete="name"
                           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('admin_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="admin_email" class="block text-sm font-medium text-slate-700">Work email</label>
                    <input id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" required maxlength="150" autocomplete="email"
                           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('admin_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>
            </fieldset>

            <button type="submit"
                    class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Create workspace
            </button>
        </form>
    </div>
</x-layouts.guest>
