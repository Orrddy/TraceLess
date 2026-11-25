<?php

namespace App\Providers;

use App\Database\NeonPostgresConnector;
use App\Services\OutboundEmailRecorder;
use App\Support\DestructiveCommandGuard;
use App\Support\ProductionSafetyGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        // Every production deployment terminates TLS at a proxy that may not
        // forward the request scheme. Generated URLs must stay https:// or the
        // browser drops the secure session cookie on the first redirect.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Signed URLs must use the canonical host/scheme or a proxy-level
            // http -> https redirect will invalidate the signature.
            if (config('app.url')) {
                URL::forceRootUrl(config('app.url'));
            }
        }

        // Refuse to run production with development conveniences enabled.
        // No-op in local, testing and any non-production environment.
        (new ProductionSafetyGuard)->enforce();

        // Resend selected but its key missing (a half-finished deployment) must
        // not take sign-in down. Fall back to the authenticated DirectAdmin
        // mailbox when one is configured, and use its address as the sender so
        // the relay accepts the message. Prefer the Resend key once it is set.
        if (config('mail.default') === 'resend' && blank(config('services.resend.key'))) {
            $smtp = config('mail.mailers.smtp');

            if (! empty($smtp['username']) && ! empty($smtp['password'])) {
                config([
                    'mail.default' => 'smtp',
                    'mail.from.address' => $smtp['username'],
                ]);

                Log::warning('mail_resend_key_missing_using_smtp_fallback', [
                    'from' => $smtp['username'],
                ]);
            }
        }

        // Refuse data-destroying commands against the Neon production branch.
        Event::listen(CommandStarting::class, fn (CommandStarting $event) => (new DestructiveCommandGuard)->handle($event));

        // Operations telemetry: record outbound mail and prove the queue worker
        // is alive. Looping fires on every worker loop, including idle polls, so
        // a quiet queue does not look like a dead worker.
        Event::listen(MessageSent::class, fn (MessageSent $event) => app(OutboundEmailRecorder::class)->recordSent($event));
        Event::listen(Looping::class, fn () => Cache::put('heartbeat:queue', now()->toIso8601String(), 3600));

        // A notification mail failure is otherwise invisible: the queue job fails
        // but /admin/email only knows about OTP transport errors.
        Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
            $exception = $event->data['exception'] ?? null;
            $notifiable = $event->notifiable;
            $email = is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')
                ? $notifiable->routeNotificationFor('mail', $event->notification)
                : null;

            if ($event->channel !== 'mail' || ! $exception instanceof Throwable || ! is_string($email) || $email === '') {
                return;
            }

            app(OutboundEmailRecorder::class)->recordFailed($email, class_basename($event->notification), $exception);
        });
    }
}
