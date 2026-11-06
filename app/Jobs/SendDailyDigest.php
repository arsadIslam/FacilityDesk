<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserRole;
use App\Jobs\Middleware\SetTenantContext;
use App\Models\User;
use App\Notifications\DailyDigest;
use App\Services\DailyDigestBuilder;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * One tenant's digest. The numbers are computed once and mailed to every
 * active supervisor.
 */
final class SendDailyDigest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $tenantId)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SetTenantContext($this->tenantId)];
    }

    public function handle(TenantContext $context, DailyDigestBuilder $builder): void
    {
        $tenant = $context->tenant();

        if ($tenant === null || ! $tenant->isActive()) {
            return;
        }

        $supervisors = User::query()->where('is_active', true)->role(UserRole::Supervisor->value)->get();

        if ($supervisors->isEmpty()) {
            return;
        }

        Notification::send($supervisors, new DailyDigest($tenant, $builder->build($tenant, CarbonImmutable::now())));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Daily digest failed', ['tenant_id' => $this->tenantId, 'error' => $exception->getMessage()]);
    }
}
