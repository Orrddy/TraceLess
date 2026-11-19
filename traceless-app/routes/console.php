<?php

use App\Jobs\DispatchDueMonitoringScans;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchDueMonitoringScans)->hourly()->name('traceless-monitoring');
Schedule::command('traceless:prune')->dailyAt('03:15')->name('traceless-retention');
Schedule::command('scans:reap-stale')->everyTenMinutes()->name('traceless-reap-scans');

// Proof the scheduler is running, shown on the operations dashboard.
Schedule::call(function () {
    Cache::put('heartbeat:schedule', now()->toIso8601String(), 3600);
})->everyMinute()->name('traceless-heartbeat');

// Watchdog for silent failures (dead worker, failed jobs, mail errors).
Schedule::command('ops:alert')->everyTenMinutes()->name('traceless-ops-alert');
