<?php

namespace App\Providers;

use App\Database\NeonPostgresConnector;
use App\Support\ProductionSafetyGuard;
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
    }
}
