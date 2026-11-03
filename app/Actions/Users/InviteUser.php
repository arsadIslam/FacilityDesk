<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InviteUser
{
    public function __construct(private readonly SendInvitation $sendInvitation) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $attributes
     */
    public function handle(array $attributes, UserRole $role, ?int $clientId = null): User
    {
        $user = DB::transaction(function () use ($attributes, $role, $clientId): User {
            $user = new User([
                ...$attributes,
                // Unusable until the invitee sets their own password.
                'password' => Str::password(64),
            ]);
            $user->client_id = $role === UserRole::ClientUser ? $clientId : null;
            $user->save();

            $user->assignRole($role->value);

            return $user;
        });

        $this->sendInvitation->handle($user);

        return $user;
    }
}
