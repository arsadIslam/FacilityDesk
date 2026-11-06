<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

final class SeedDemo extends Command
{
    protected $signature = 'demo:seed
        {--large : Also add 50,000 work orders to the "acme" workspace for benchmarking}
        {--fresh : Drop all tables and migrate first}';

    protected $description = 'Seed the demo workspaces (acme, globex) with role-wise logins (password: password)';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        } elseif (Tenant::query()->whereIn('slug', ['acme', 'globex'])->exists()) {
            $this->error('The demo workspaces already exist. Use --fresh to start over (this wipes the database).');

            return self::FAILURE;
        }

        $this->call('tenancy:provision');

        $seeder = $this->laravel->make(DemoSeeder::class);
        $seeder->large = (bool) $this->option('large');
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->__invoke();

        $this->newLine();
        $this->info('Demo ready. Sign in at http://acme.'.config('tenancy.domain').'/login with any of:');
        $this->table(['Role', 'Email'], [
            ['Tenant Admin', 'admin@acme.test'],
            ['Supervisor', 'supervisor@acme.test'],
            ['Technician', 'tech@acme.test'],
            ['Client User', 'client@acme.test'],
        ]);
        $this->line('Password for every account: '.DemoSeeder::PASSWORD.'. Second workspace: globex (same pattern).');

        return self::SUCCESS;
    }
}
