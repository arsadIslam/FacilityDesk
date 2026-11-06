<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Queries\WorkOrderListQuery;
use App\Services\DashboardStats;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Times the real list and dashboard queries (median of N runs) and counts
 * the SQL statements each one issues, to catch slow queries and N+1s.
 * Run against `php artisan demo:seed --large` on MySQL for meaningful numbers.
 */
final class BenchmarkWorkOrders extends Command
{
    protected $signature = 'app:benchmark {--tenant=acme} {--runs=15}';

    protected $description = 'Benchmark work order list and dashboard queries (median ms, query count)';

    public function handle(TenantContext $context, WorkOrderListQuery $list, DashboardStats $stats): int
    {
        $tenant = Tenant::query()->where('slug', $this->option('tenant'))->first();

        if ($tenant === null) {
            $this->error('Tenant not found. Seed with: php artisan demo:seed --large');

            return self::FAILURE;
        }

        return $context->run($tenant, function (Tenant $tenant) use ($list, $stats): int {
            $supervisor = User::query()->role(UserRole::Supervisor->value)->firstOrFail();
            $technician = User::query()->role(UserRole::Technician->value)->firstOrFail();
            $siteId = (int) Site::query()->value('id');
            $number = (string) WorkOrder::query()->latest('id')->value('number');
            $now = CarbonImmutable::now();

            $page = fn (User $user, array $filters, int $page = 1) => fn () => $list->build($user, $filters)->paginate(20, ['*'], 'page', $page)->count();

            $scenarios = [
                'List: newest, page 1' => $page($supervisor, []),
                'List: newest, page 100' => $page($supervisor, [], 100),
                'List: status = in progress' => $page($supervisor, ['status' => WorkOrderStatus::InProgress->value]),
                'List: one site' => $page($supervisor, ['site_id' => $siteId]),
                'List: SLA breached only' => $page($supervisor, ['breached' => true]),
                'List: search by number' => $page($supervisor, ['search' => $number]),
                'List: search by title' => $page($supervisor, ['search' => 'leak']),
                'Technician: my jobs (cursor)' => fn () => WorkOrder::query()
                    ->where('assigned_to', $technician->id)
                    ->whereNotIn('status', WorkOrderStatus::finishedValues())
                    ->with(WorkOrderListQuery::RELATIONS)
                    ->orderBy('resolution_due_at')->orderBy('id')
                    ->cursorPaginate(20)->count(),
                'Dashboard: this month (uncached)' => function () use ($stats, $tenant, $now): int {
                    DashboardStats::bust($tenant->id);
                    $stats->forPeriod($tenant, $now->startOfMonth(), $now);

                    return 1;
                },
            ];

            $this->info(sprintf('%s: %s work orders, %d runs each', $tenant->slug, number_format(WorkOrder::query()->count()), (int) $this->option('runs')));

            $rows = [];
            foreach ($scenarios as $name => $scenario) {
                [$ms, $queries, $result] = $this->measure($scenario, max(1, (int) $this->option('runs')));
                $rows[] = [$name, number_format($ms, 1), $queries, $result];
            }

            $this->table(['Scenario', 'Median ms', 'Queries', 'Rows'], $rows);

            return self::SUCCESS;
        });
    }

    /**
     * @return array{float, int, int}
     */
    private function measure(Closure $scenario, int $runs): array
    {
        $scenario(); // warm-up (connection, opcache, query plan)

        $times = [];
        $queries = 0;
        $result = 0;

        for ($i = 0; $i < $runs; $i++) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $start = hrtime(true);
            $result = (int) $scenario();
            $times[] = (hrtime(true) - $start) / 1e6;
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        sort($times);

        return [$times[intdiv(count($times), 2)], $queries, $result];
    }
}
