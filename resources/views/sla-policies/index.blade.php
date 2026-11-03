<x-layouts.app title="SLA policies">
    <x-page-header title="SLA policies" subtitle="Response and resolution targets per priority, in minutes." />

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Priority</th>
                    <th class="px-4 py-3">Respond within</th>
                    <th class="px-4 py-3">Resolve within</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($policies as $policy)
                    @can('update', $policy)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $policy->priority->label() }}</td>
                            <td class="px-4 py-3" colspan="3">
                                <form method="POST" action="{{ route('sla-policies.update', $policy) }}" class="flex flex-wrap items-start gap-3">
                                    @csrf @method('PUT')
                                    <input type="number" name="response_minutes" min="1" aria-label="{{ $policy->priority->label() }} response minutes"
                                           value="{{ $policy->response_minutes }}" class="w-32 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                                    <input type="number" name="resolution_minutes" min="1" aria-label="{{ $policy->priority->label() }} resolution minutes"
                                           value="{{ $policy->resolution_minutes }}" class="w-32 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                                    <x-button>Save</x-button>
                                </form>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $policy->priority->label() }}</td>
                            <td class="px-4 py-3">{{ $policy->response_minutes }} min</td>
                            <td class="px-4 py-3">{{ $policy->resolution_minutes }} min</td>
                            <td></td>
                        </tr>
                    @endcan
                @endforeach
            </tbody>
        </table>
    </x-card>
    @if ($errors->any())
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
        </div>
    @endif
</x-layouts.app>
