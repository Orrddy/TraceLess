<?php

use App\Http\Controllers\IpIntelligenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
|
| Bearer-less JSON consumed by the marketing page. Rate limited per client IP;
| the endpoint itself only reads the persistent cache and queues one job.
|
*/

Route::get('/ip-intelligence', IpIntelligenceController::class)
    ->middleware('throttle:180,1')
    ->name('api.ip-intelligence');
