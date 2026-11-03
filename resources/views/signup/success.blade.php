<x-layouts.guest title="Workspace ready">
    <div class="mx-auto max-w-lg rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $workspace['name'] }} is ready</h1>
        <p class="mt-2 text-sm text-slate-600">Your workspace lives at its own address. Bookmark it — your team will sign in there.</p>
        <a href="{{ $workspace['url'] }}"
           class="mt-6 inline-block rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            {{ $workspace['url'] }}
        </a>
    </div>
</x-layouts.guest>
