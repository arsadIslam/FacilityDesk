<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;

final class TenantPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        $key = (string) $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7));
        }

        return new TenantDatabaseTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
