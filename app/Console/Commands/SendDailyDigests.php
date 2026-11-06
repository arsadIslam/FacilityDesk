<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Jobs\SendDailyDigest;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Scheduled hourly. Queues a digest for every active tenant where it is now
 * the 08:00 hour locally, so each tenant gets it at 8 in its own timezone
 * (including half-hour zones such as Asia/Kolkata).
 *
 * A per-tenant, per-local-day cache key makes a second run in the same day a
 * no-op, so a retried or overlapping schedule never mails twice.
 */
final class SendDailyDigests extends Command
{
    public const SEND_AT_HOUR = 8;

    protected $signature = 'digest:daily
        {--tenant= : Only this tenant slug}
        {--force : Send now regardless of the local time or an earlier send today}';

    protected $description = "Queue the supervisors' daily summary email for tenants where it is 08:00 locally";

    public function handle(): int
    {
        $queued = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active->value)
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use (&$queued): void {
                $local = now()->setTimezone($tenant->timezone);
                $force = (bool) $this->option('force');

                if (! $force && $local->hour !== self::SEND_AT_HOUR) {
                    return;
                }

                $key = "digest:{$tenant->id}:{$local->toDateString()}";

                if (! $force && ! Cache::add($key, true, now()->addDay())) {
                    return;
                }

                SendDailyDigest::dispatch($tenant->id);
                $queued++;
            });

        $this->info("Queued {$queued} daily digest(s).");

        return self::SUCCESS;
    }
}
