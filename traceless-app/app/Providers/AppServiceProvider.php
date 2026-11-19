<?php

namespace App\Providers;

use App\Database\NeonPostgresConnector;
use App\Services\OutboundEmailRecorder;
use App\Support\DestructiveCommandGuard;
use App\Support\ProductionSafetyGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Neon (and other proxied Postgres providers) need libpq options in
        // the DSN so clients without SNI can identify their compute endpoint.
        $this->app->bind('db.connector.pgsql', fn () => new NeonPostgresConnector);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Refuse to run production with development conveniences enabled.
        // No-op in local, testing and any non-production environment.
        (new ProductionSafetyGuard)->enforce();

        // Refuse data-destroying commands against the Neon production branch.
        Event::listen(CommandStarting::class, fn (CommandStarting $event) => (new DestructiveCommandGuard)->handle($event));

        // Operations telemetry: record outbound mail and prove the queue worker
        // is alive. Looping fires on every worker loop, including idle polls, so
        // a quiet queue does not look like a dead worker.
        Event::listen(MessageSent::class, fn (MessageSent $event) => app(OutboundEmailRecorder::class)->recordSent($event));
        Event::listen(Looping::class, fn () => Cache::put('heartbeat:queue', now()->toIso8601String(), 3600));
    }
}
