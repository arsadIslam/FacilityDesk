<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

final class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
