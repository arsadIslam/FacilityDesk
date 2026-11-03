<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\URL;
use LogicException;

final class SendInvitation
{
    public function handle(User $user): void
    {
        $tenant = $user->loadMissing('tenant')->tenant ?? throw new LogicException('Only tenant users can be invited.');

        $url = URL::temporarySignedRoute(
            'invitations.show',
            now()->addHours((int) config('tenancy.invitation_expiry_hours')),
            ['tenant' => $tenant->slug, 'user' => $user->id],
        );

        $user->notify(new UserInvitation($tenant->name, $url));
    }
}
