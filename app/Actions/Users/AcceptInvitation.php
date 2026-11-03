<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final class AcceptInvitation
{
    public function handle(User $user, string $password): void
    {
        $user->password = $password;
        $user->email_verified_at = now();
        $user->save();
    }
}
