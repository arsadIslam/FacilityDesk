<x-layouts.app title="Users">
    <x-page-header title="Users" subtitle="Everyone who can sign in to this workspace.">
        @can('create', App\Models\User::class)
            <x-slot:actions>
                <x-button :href="route('users.create')">Invite user</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Last sign-in</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $user->name }}</div>
                            <div class="text-slate-500">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            {{ $user->role()?->label() ?? '—' }}
                            @if ($user->client) <div class="text-xs text-slate-500">{{ $user->client->name }}</div> @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->hasPendingInvitation())
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Invited</span>
                            @else
                                <x-status-badge :active="$user->is_active" />
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3">
                                @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                                    @if ($user->hasPendingInvitation())
                                        <form method="POST" action="{{ route('users.invitation', $user) }}">
                                            @csrf
                                            <button class="font-medium text-indigo-600 hover:text-indigo-500">Resend invite</button>
                                        </form>
                                    @endif
                                    @if (! $user->is_active)
                                        <form method="POST" action="{{ route('users.activate', $user) }}">
                                            @csrf @method('PATCH')
                                            <button class="font-medium text-green-700 hover:text-green-600">Activate</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('deactivate', $user)
                                    @if ($user->is_active)
                                        <form method="POST" action="{{ route('users.deactivate', $user) }}" onsubmit="return confirm('Deactivate {{ $user->name }}?')">
                                            @csrf @method('PATCH')
                                            <button class="font-medium text-red-600 hover:text-red-500">Deactivate</button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No users yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
