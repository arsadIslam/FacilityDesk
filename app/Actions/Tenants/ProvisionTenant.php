<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\Priority;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Gives a tenant everything it needs to work: roles, one SLA policy per
 * priority and the default categories.
 *
 * Idempotent, so it runs at signup and again (tenancy:provision) to backfill
 * existing tenants when later releases add permissions or defaults. It never
 * overwrites what a tenant has customised.
 */
final class ProvisionTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SyncTenantRoles $syncTenantRoles,
    ) {}

    public function handle(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $this->syncTenantRoles->handle($tenant);

            $this->context->run($tenant, function (Tenant $tenant): void {
                $this->createMissingSlaPolicies($tenant);
                $this->createDefaultCategories($tenant);
            });
        });
    }

    private function createMissingSlaPolicies(Tenant $tenant): void
    {
        $existing = $tenant->slaPolicies()->pluck('priority')->map(fn (Priority $priority) => $priority->value);

        foreach (Priority::cases() as $priority) {
            if ($existing->contains($priority->value)) {
                continue;
            }

            $policy = new SlaPolicy([
                'response_minutes' => $priority->defaultSla()['response'],
                'resolution_minutes' => $priority->defaultSla()['resolution'],
            ]);
            $policy->priority = $priority;
            $tenant->slaPolicies()->save($policy);
        }
    }

    /**
     * Only for a tenant with no categories yet, so a default the tenant
     * renamed or deactivated is not resurrected.
     */
    private function createDefaultCategories(Tenant $tenant): void
    {
        if ($tenant->categories()->exists()) {
            return;
        }

        foreach (Category::DEFAULTS as $name) {
            $tenant->categories()->create(['name' => $name]);
        }
    }
}
