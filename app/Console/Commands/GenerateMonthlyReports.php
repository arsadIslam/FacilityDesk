<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reports\GenerateMonthlyReports as GenerateMonthlyReportsAction;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Last month's reports for every active tenant, one batch per tenant.
 * Loops tenant by tenant, setting the context for each.
 *
 * Scheduled hourly: without --month a tenant is only processed once it is
 * the 1st locally, so "last month" is complete in that tenant's timezone
 * (a UTC-only run would hit tenants behind UTC on the previous day).
 */
final class GenerateMonthlyReports extends Command
{
    protected $signature = 'reports:monthly {--month= : YYYY-MM, defaults to last month} {--tenant= : Only this tenant slug}';

    protected $description = 'Queue monthly PDF/Excel reports for every client (safe to re-run)';

    public function handle(GenerateMonthlyReportsAction $generate, TenantContext $context): int
    {
        $option = $this->option('month');
        $month = is_string($option) ? CarbonImmutable::createFromFormat('!Y-m', $option) : null;

        if (is_string($option) && ! $month instanceof CarbonImmutable) {
            $this->error('Use --month=YYYY-MM.');

            return self::FAILURE;
        }

        Tenant::query()
            ->where('status', TenantStatus::Active->value)
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($generate, $context, $month): void {
                $local = CarbonImmutable::now()->setTimezone($tenant->timezone);

                if ($month === null && $local->day !== 1) {
                    return;
                }

                $target = $month ?? $local->subMonthNoOverflow();

                $batch = $context->run($tenant, fn (Tenant $tenant) => $generate->handle($tenant, $target));

                $this->line(sprintf('%s %s: %s', $tenant->slug, $target->format('Y-m'), $batch === null ? 'nothing to do' : "{$batch->totalJobs} report(s) queued"));
            });

        return self::SUCCESS;
    }
}
