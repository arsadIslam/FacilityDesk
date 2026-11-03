<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SetUserActive
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, User $user, bool $active): void
    {
        if (! $active && $actor->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot deactivate your own account.']);
        }

        $user->is_active = $active;
        $user->save();
    }
}
