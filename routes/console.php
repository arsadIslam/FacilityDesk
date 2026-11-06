<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
|
| Run with `php artisan schedule:work` locally, or a cron entry calling
| `php artisan schedule:run` every minute in production. onOneServer()
| needs a shared cache (Redis) when several app servers run the scheduler.
|
*/

Schedule::command('sla:check-breaches')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly, because 08:00 happens at a different UTC hour for each tenant.
Schedule::command('digest:daily')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
