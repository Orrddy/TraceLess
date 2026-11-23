<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Services\OutboundEmailRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Operational watchdog. Runs on the scheduler and emails administrators when a
 * silent-failure condition appears: a dead queue worker, a spike in failed jobs,
 * or outgoing mail that is failing.
 *
 * Rate limited to one alert per hour so a persistent problem does not flood inboxes.
 */
class OpsAlert extends Command
{
    protected $signature = 'ops:alert {--force : Send even if an alert was sent in the last hour}';

    protected $description = 'Email administrators when operations look broken';

    private const ALERT_COOLDOWN = 3600;

    private const QUEUE_STALE_MINUTES = 10;

    public function handle(): int
    {
        $problems = [];

        $queueBeat = Cache::get('heartbeat:queue');
        $queueAge = $this->ageInMinutes($queueBeat);

        if ($queueAge === null || $queueAge > self::QUEUE_STALE_MINUTES) {
            $problems[] = $queueAge === null
                ? 'The queue worker has never reported a heartbeat.'
                : "The queue worker has not reported for {$queueAge} minutes.";
        }

        $failedJobs = DB::table('failed_jobs')->count();
        $previousFailed = (int) Cache::get('ops:failed_jobs_count', 0);

        if ($failedJobs > $previousFailed) {
            $problems[] = 'Failed jobs increased from '.$previousFailed.' to '.$failedJobs.'.';
        }

        Cache::put('ops:failed_jobs_count', $failedJobs, 86400);

        $mailFailures = OutboundEmail::where('status', 'failed')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($mailFailures > 0) {
            $problems[] = "{$mailFailures} outbound email failure(s) in the last hour.";
        }

        if ($problems === []) {
            $this->info('Operations look healthy.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && Cache::has('ops:alert:last_sent')) {
            $this->warn('Problems detected, but the alert cooldown is active.');

            return self::SUCCESS;
        }

        $message = "TraceLess operations alert\n\n".implode("\n", array_map(fn ($p) => '- '.$p, $problems));

        AuditLog::create([
            'user_id' => null,
            'action' => 'ops.alert',
            'details' => mb_substr(implode(' | ', $problems), 0, 1000),
            'ip_address' => null,
        ]);

        $recipients = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->whereNull('suspended_at')
            ->pluck('email');

        foreach ($recipients as $email) {
            try {
                Mail::raw($message, function ($mail) use ($email, $problems) {
                    $mail->to($email)->subject('TraceLess operations alert: '.count($problems).' issue(s)');
                });
            } catch (Throwable $exception) {
                // The watchdog is the last line of defence; if its own mail is
                // failing that must reach the delivery log too.
                app(OutboundEmailRecorder::class)->recordFailed($email, 'TraceLess operations alert', $exception);
                $this->warn("Could not alert {$email}: ".$exception->getMessage());
            }
        }

        Cache::put('ops:alert:last_sent', now()->toIso8601String(), self::ALERT_COOLDOWN);

        $this->warn('Alert sent: '.implode(' | ', $problems));

        return self::SUCCESS;
    }

    private function ageInMinutes(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return (int) Carbon::parse($value)->diffInMinutes(now(), true);
        } catch (Throwable) {
            return null;
        }
    }
}
