<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, per-tenant, per-year numbers such as WO-2026-00123.
 *
 * MAX(number) + 1 races under concurrent requests; instead one counter row
 * per (tenant, year) is incremented under SELECT ... FOR UPDATE, so two
 * requests for the same tenant serialise on that single row only. The
 * UNIQUE (tenant_id, number) index is the backstop.
 */
final class WorkOrderNumberGenerator
{
    public function next(Tenant $tenant, CarbonInterface $at): string
    {
        // The year as the tenant sees it: a ticket raised at 00:30 IST on 1 Jan is next year's.
        $year = $at->copy()->setTimezone($tenant->timezone)->year;

        $number = DB::transaction(function () use ($tenant, $year): int {
            $sequence = DB::table('work_order_sequences')->where(['tenant_id' => $tenant->id, 'year' => $year]);

            DB::table('work_order_sequences')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'year' => $year,
                'last_number' => 0,
            ]);

            $next = (int) (clone $sequence)->lockForUpdate()->value('last_number') + 1;
            (clone $sequence)->update(['last_number' => $next]);

            return $next;
        });

        return sprintf('WO-%d-%05d', $year, $number);
    }
}
