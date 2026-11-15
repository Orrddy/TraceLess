<?php

use App\Jobs\DispatchDueMonitoringScans;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchDueMonitoringScans)->hourly()->name('traceless-monitoring');
Schedule::command('traceless:prune')->dailyAt('03:15')->name('traceless-retention');
