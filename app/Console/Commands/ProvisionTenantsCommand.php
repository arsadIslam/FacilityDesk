<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class ProvisionTenantsCommand extends Command
{
    protected $signature = 'tenancy:provision {tenant? : Tenant slug; all tenants when omitted}';

    protected $description = 'Sync roles and permissions and create missing tenant defaults (safe to re-run)';

    public function handle(ProvisionTenant $provisionTenant): int
    {
        $query = Tenant::query()->orderBy('id');

        if ($slug = $this->argument('tenant')) {
            $query->where('slug', $slug);
        }

        $count = 0;

        $query->each(function (Tenant $tenant) use ($provisionTenant, &$count): void {
            $provisionTenant->handle($tenant);
            $this->line("Provisioned <info>{$tenant->slug}</info>");
            $count++;
        });

        if ($count === 0) {
            $this->warn('No tenants found.');
        }

        return self::SUCCESS;
    }
}
