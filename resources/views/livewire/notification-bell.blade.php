<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" wire:poll.30s>
    <button type="button" @click="open = ! open" class="relative rounded-md p-1.5 text-slate-600 hover:bg-slate-100 hover:text-slate-900"
            aria-label="Notifications{{ $this->unreadCount ? ', '.$this->unreadCount.' unread' : '' }}" :aria-expanded="open">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute right-0 z-20 mt-2 w-80 rounded-lg border border-slate-200 bg-white shadow-lg">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2">
            <span class="text-sm font-semibold text-slate-900">Notifications</span>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Mark all read</button>
            @endif
        </div>
        <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($this->latest as $notification)
                <li wire:key="notification-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')"
                            @class(['block w-full px-4 py-3 text-left text-sm hover:bg-slate-50', 'bg-indigo-50/50' => $notification->read_at === null])>
                        <span class="block font-medium text-slate-900">{{ $notification->data['message'] ?? 'Notification' }}</span>
                        <span class="block truncate text-slate-600">{{ $notification->data['title'] ?? '' }}</span>
                        <span class="mt-0.5 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">You're all caught up.</li>
            @endforelse
        </ul>
    </div>
</div>
