<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;

/**
 * Holds the tenant for the current request, job or command.
 *
 * Registered as a scoped singleton, so the container flushes it between
 * requests (Octane) and between jobs (queue worker).
 *
 * Also keeps spatie/laravel-permission's team id in sync, so role and
 * permission checks follow the tenant wherever the context is set.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        setPermissionsTeamId($tenant?->getKey());
    }

    public function clear(): void
    {
        $this->set(null);
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Run a callback as the given tenant, restoring the previous context afterwards.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->set($previous);
        }
    }
}
