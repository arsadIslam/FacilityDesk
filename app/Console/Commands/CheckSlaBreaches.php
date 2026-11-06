<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Enums\WorkOrderStatus;
use App\Events\SlaBreached;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Services\SlaCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Flags work orders that have just missed a target and alerts once per miss.
 *
 * Idempotent: a flag only ever goes false → true, under a row lock, and the
 * alert fires only on that flip, so overlapping or repeated runs can never
 * send the same alert twice. breach_notified_at records when it went out.
 *
 * Works across all tenants with one indexed query (explicitly unscoped);
 * listeners restore each tenant's context via SetTenantContext.
 */
final class CheckSlaBreaches extends Command
{
    protected $signature = 'sla:check-breaches';

    protected $description = 'Flag work orders that missed their SLA and alert supervisors (safe to re-run)';

    public function handle(SlaCalculator $sla): int
    {
        $now = CarbonImmutable::now();
        $alerts = 0;

        $this->candidates($now)->chunkById(200, function ($rows) use ($sla, $now, &$alerts): void {
            foreach ($rows as $row) {
                $alerts += $this->check((int) $row->id, $sla, $now) ? 1 : 0;
            }
        });

        $this->info("SLA check complete: {$alerts} work order(s) newly breached.");

        return self::SUCCESS;
    }

    /**
     * Cheap pre-filter; check() decides under a lock using SlaCalculator.
     *
     * @return Builder<WorkOrder>
     */
    private function candidates(CarbonImmutable $now): Builder
    {
        $running = array_diff(
            array_map(fn (WorkOrderStatus $s) => $s->value, WorkOrderStatus::cases()),
            [...WorkOrderStatus::finishedValues(), WorkOrderStatus::OnHold->value],
        );

        return WorkOrder::query()
            ->withoutGlobalScope('tenant')
            ->select(['id'])
            ->whereIn('tenant_id', Tenant::query()->where('status', TenantStatus::Active->value)->select('id'))
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q
                    ->where('is_response_breached', false)
                    ->whereNull('responded_at')
                    ->where('status', '!=', WorkOrderStatus::Cancelled->value)
                    ->where('response_due_at', '<', $now))
                ->orWhere(fn (Builder $q) => $q
                    ->where('is_resolution_breached', false)
                    ->whereIn('status', $running)
                    ->where('resolution_due_at', '<', $now)));
    }

    private function check(int $id, SlaCalculator $sla, CarbonImmutable $now): bool
    {
        return DB::transaction(function () use ($id, $sla, $now): bool {
            $workOrder = WorkOrder::query()->withoutGlobalScope('tenant')->lockForUpdate()->find($id);

            if ($workOrder === null) {
                return false;
            }

            $breaches = [];

            if (! $workOrder->is_response_breached
                && $workOrder->status !== WorkOrderStatus::Cancelled
                && $sla->isResponseBreached($workOrder->responded_at, $workOrder->response_due_at, $now)) {
                $workOrder->is_response_breached = true;
                $breaches[] = 'response';
            }

            if (! $workOrder->is_resolution_breached
                && ! $workOrder->status->isFinished()
                && $sla->isResolutionBreached($workOrder->status, $workOrder->resolved_at, $workOrder->resolution_due_at, $now)) {
                $workOrder->is_resolution_breached = true;
                $breaches[] = 'resolution';
            }

            if ($breaches === []) {
                return false;
            }

            $workOrder->breach_notified_at = now();
            $workOrder->save();

            SlaBreached::dispatch($workOrder, $breaches);

            return true;
        });
    }
}
