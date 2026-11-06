<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Header bell: unread count plus the latest notifications. Polls every 30s.
 * Notifications belong to the user (morph by user id), so no other user's
 * rows can ever be read or marked here.
 */
final class NotificationBell extends Component
{
    public const LATEST = 8;

    #[Computed]
    public function unreadCount(): int
    {
        return $this->user()->unreadNotifications()->count();
    }

    /**
     * @return DatabaseNotificationCollection<int, DatabaseNotification>
     */
    #[Computed]
    public function latest(): DatabaseNotificationCollection
    {
        return $this->user()->notifications()->latest()->limit(self::LATEST)->get();
    }

    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        // Only ever a path on this subdomain, never an absolute URL: no open redirect.
        $path = (string) ($notification->data['path'] ?? '/');
        $this->redirect(str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : '/', navigate: true);
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->unreadCount, $this->latest);
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
