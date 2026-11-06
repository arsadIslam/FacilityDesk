<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Priority;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared by the Livewire form and (Phase 6) the API request,
 * so web and API can never drift apart.
 */
final class WorkOrderRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function create(User $user, ?int $siteId): array
    {
        $sites = TenantRules::exists('sites')->where('is_active', true)->withoutTrashed();

        // Client Users may only raise work orders for their own client's sites.
        if ($user->hasUserRole(UserRole::ClientUser)) {
            $sites->where('client_id', $user->client_id ?? 0);
        }

        return [
            'site_id' => ['required', 'integer', $sites],
            'location_id' => ['required', 'integer', TenantRules::exists('locations')->where('site_id', $siteId ?? 0)->withoutTrashed()],
            'category_id' => ['required', 'integer', TenantRules::exists('categories')->where('is_active', true)],
            'asset_id' => ['nullable', 'integer', TenantRules::exists('assets')->where('site_id', $siteId ?? 0)->withoutTrashed()],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Client Users cannot pick a priority (everything would be "critical");
     * their work orders take the category default.
     */
    public static function canChoosePriority(User $user): bool
    {
        return ! $user->hasUserRole(UserRole::ClientUser);
    }
}
