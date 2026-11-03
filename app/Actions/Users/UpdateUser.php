<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateUser
{
    /**
     * @param  array{name: string, phone?: string|null}  $attributes
     * @param  list<int>  $skillIds
     */
    public function handle(User $user, array $attributes, UserRole $role, ?int $clientId, array $skillIds): User
    {
        return DB::transaction(function () use ($user, $attributes, $role, $clientId, $skillIds): User {
            $user->fill($attributes);
            $user->client_id = $role === UserRole::ClientUser ? $clientId : null;
            $user->save();

            $user->syncRoles([$role->value]);

            // Skills only mean something for technicians; drop them on a role change.
            $user->skills()->syncWithPivotValues(
                $role === UserRole::Technician ? $skillIds : [],
                ['tenant_id' => $user->tenant_id],
            );

            return $user;
        });
    }
}
