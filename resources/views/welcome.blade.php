<x-layouts.guest>
    <x-slot:nav>
        <a href="{{ route('signup.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Start free trial</a>
    </x-slot:nav>

    <section class="mx-auto max-w-2xl text-center">
        <h1 class="text-4xl font-bold tracking-tight text-slate-900">Every complaint, tracked to closure.</h1>
        <p class="mt-4 text-lg text-slate-600">
            FacilityDesk helps facility management companies run client sites, work orders and SLAs from one place.
        </p>
        <a href="{{ route('signup.create') }}"
           class="mt-8 inline-block rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            Create your workspace
        </a>
    </section>
</x-layouts.guest>
