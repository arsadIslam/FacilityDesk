<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Jobs\GenerateMonthlyReport;
use App\Models\Client;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Queues one GenerateMonthlyReport per active client as a single batch.
 * Clients that already have a finished or running report for the month are
 * skipped, so re-running (or the 1st-of-month schedule) is safe.
 */
final class GenerateMonthlyReports
{
    public function handle(Tenant $tenant, CarbonImmutable $month, ?User $requestedBy = null): ?Batch
    {
        $month = $month->setTimezone($tenant->timezone)->startOfMonth();

        $reports = DB::transaction(function () use ($tenant, $month, $requestedBy) {
            $alreadyDone = Report::query()
                ->whereDate('month', $month->toDateString())
                ->whereIn('status', [ReportStatus::Pending->value, ReportStatus::Processing->value, ReportStatus::Completed->value])
                ->pluck('client_id');

            return Client::query()
                ->where('is_active', true)
                ->whereNotIn('id', $alreadyDone)
                ->orderBy('name')
                ->get()
                ->map(function (Client $client) use ($tenant, $month, $requestedBy): Report {
                    $report = new Report;
                    $report->forceFill([
                        'tenant_id' => $tenant->id,
                        'client_id' => $client->id,
                        'month' => $month->toDateString(),
                        'status' => ReportStatus::Pending,
                        'requested_by' => $requestedBy?->id,
                    ])->save();

                    return $report;
                });
        });

        if ($reports->isEmpty()) {
            return null;
        }

        $batch = Bus::batch($reports->map(fn (Report $r) => new GenerateMonthlyReport($r->id, $tenant->id))->all())
            ->name("Monthly reports {$tenant->slug} {$month->format('Y-m')}")
            ->onQueue('reports')
            // One client's failure must not stop the others.
            ->allowFailures()
            ->dispatch();

        Report::query()->whereKey($reports->modelKeys())->update(['batch_id' => $batch->id]);

        return $batch;
    }
}
