<?php

namespace Tests\Feature;

use App\Jobs\RunScanJob;
use App\Livewire\Admin\EmailDelivery;
use App\Livewire\Admin\FailedJobs;
use App\Livewire\Admin\Overview;
use App\Livewire\Admin\ScanInspector;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\OutboundEmail;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\OtpService;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function client(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLIENT]);
    }

    private function profileFor(User $user): IdentityProfile
    {
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Ops Person',
            'primary_email' => $user->email,
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');

        return $profile;
    }

    private function scanFor(IdentityProfile $profile, array $overrides = []): Scan
    {
        return Scan::create(array_merge([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Completed',
            'demo_mode' => false,
            'sources_checked' => 2,
            'findings_count' => 0,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ], $overrides));
    }

    private function failedJob(string $uuid, string $exception = 'RuntimeException: boom'): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'scans',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'App\\Jobs\\RunScanJob',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'maxTries' => 1,
                'data' => [],
                'queue' => 'scans',
            ]),
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }

    // ----------------------------------------------------------------- access

    public function test_operations_pages_are_admin_only(): void
    {
        $client = $this->client();
        $admin = $this->admin();
        $profile = $this->profileFor($client);
        $scan = $this->scanFor($profile);

        $this->get("/admin/scans/{$scan->id}")->assertRedirect('/login');
        $this->get('/admin/failed-jobs')->assertRedirect('/login');
        $this->get('/admin/email')->assertRedirect('/login');

        $this->actingAs($client)->get("/admin/scans/{$scan->id}")->assertForbidden();
        $this->actingAs($client)->get('/admin/failed-jobs')->assertForbidden();
        $this->actingAs($client)->get('/admin/email')->assertForbidden();

        $this->actingAs($admin)->get("/admin/scans/{$scan->id}")->assertOk()->assertSee('Sources');
        $this->actingAs($admin)->get('/admin/failed-jobs')->assertOk()->assertSee('Failed jobs');
        $this->actingAs($admin)->get('/admin/email')->assertOk()->assertSee('Email delivery');
    }

    public function test_clients_cannot_mount_operations_components(): void
    {
        $client = $this->client();
        $this->actingAs($client);

        $profile = $this->profileFor($client);
        $scan = $this->scanFor($profile);

        $instances = [
            fn () => (new ScanInspector)->mount($scan),
            fn () => (new FailedJobs)->mount(),
            fn () => (new EmailDelivery)->mount(),
        ];

        foreach ($instances as $instance) {
            try {
                $instance();
                $this->fail('A client mounted an operations component.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    // --------------------------------------------------------- scan inspector

    public function test_the_scan_inspector_shows_source_outcomes_and_failure_reason(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $profile = $this->profileFor($client);
        $scan = $this->scanFor($profile, [
            'status' => 'failed',
            'error_message' => 'The scanner returned an invalid payload.',
            'findings_count' => 1,
        ]);

        $scan->scanSources()->create([
            'name' => 'GitHub',
            'category' => 'developer_profile',
            'status' => 'completed',
            'outcome' => 'found',
            'http_status' => 200,
            'checked_url' => 'https://github.com/ops-person',
            'outcome_reason' => null,
            'findings_found' => 1,
        ]);

        Finding::create([
            'identity_profile_id' => $profile->id,
            'scan_id' => $scan->id,
            'source' => 'GitHub',
            'category' => 'developer_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'ops-person',
            'confidence' => 0.95,
            'risk' => 'low',
            'removal_method' => 'manual_instructions',
            'status' => 'needs_user_action',
            'title' => 'Public profile on GitHub',
            'verification_status' => 'verified',
            'discovered_at' => now(),
            'last_checked_at' => now(),
        ]);

        $this->actingAs($admin)->get("/admin/scans/{$scan->id}")
            ->assertOk()
            ->assertSee('GitHub')
            ->assertSee('https://github.com/ops-person')
            ->assertSee('The scanner returned an invalid payload.')
            ->assertSee('Public profile on GitHub');
    }

    public function test_an_admin_can_retry_a_scan(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $client = $this->client();
        $profile = $this->profileFor($client);
        $scan = $this->scanFor($profile, ['status' => 'failed']);

        Livewire::actingAs($admin)->test(ScanInspector::class, ['scan' => $scan])
            ->call('retry')
            ->assertRedirect();

        $this->assertSame(2, Scan::count());
        $retried = Scan::latest('id')->first();
        $this->assertSame($scan->type, $retried->type);
        $this->assertSame($profile->id, $retried->identity_profile_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.scan_retried']);
    }

    public function test_an_admin_can_cancel_a_running_scan_and_the_job_does_nothing(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $client = $this->client();
        $profile = $this->profileFor($client);
        $scan = $this->scanFor($profile, [
            'status' => 'checking_sources',
            'completed_at' => null,
        ]);

        Livewire::actingAs($admin)->test(ScanInspector::class, ['scan' => $scan])
            ->call('cancel')
            ->assertHasNoErrors();

        $this->assertSame('cancelled', $scan->fresh()->status);
        $this->assertNotNull($scan->fresh()->cancelled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.scan_cancelled']);

        // The queued job must not resurrect a cancelled scan.
        (new RunScanJob($scan->fresh()))->handle(app(ScanOrchestrator::class));

        $this->assertSame('cancelled', $scan->fresh()->status);
        $this->assertSame(0, Finding::count());
    }

    // -------------------------------------------------------------- failed jobs

    public function test_failed_jobs_are_listed_and_can_be_deleted_or_retried(): void
    {
        $admin = $this->admin();
        $uuidA = (string) Str::uuid();
        $uuidB = (string) Str::uuid();

        $this->failedJob($uuidA, 'App\\Jobs\\RunScanJob: timeout');
        $this->failedJob($uuidB, 'RuntimeException: other failure');

        $this->actingAs($admin)->get('/admin/failed-jobs')
            ->assertOk()
            ->assertSee('RunScanJob')
            ->assertSee('timeout');

        // delete one
        Livewire::actingAs($admin)->test(FailedJobs::class)->call('forgetJob', $uuidA);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuidA]);

        // retry the other through a database queue so it lands in jobs
        config(['queue.default' => 'database']);
        Livewire::actingAs($admin)->test(FailedJobs::class)->call('retryAll');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.jobs_retried_all']);
    }

    // ---------------------------------------------------------- email delivery

    public function test_sent_mail_is_recorded_and_surfaced_in_the_panel(): void
    {
        $admin = $this->admin();

        Mail::raw('hello', function ($message) {
            $message->to('recipient@example.com')->subject('Recorded message');
        });

        $this->assertDatabaseHas('outbound_emails', [
            'to_email' => 'recipient@example.com',
            'subject' => 'Recorded message',
            'status' => 'sent',
        ]);

        OutboundEmail::create([
            'to_email' => 'failed@example.com',
            'subject' => 'Sign-in code',
            'status' => 'failed',
            'error' => 'Connection could not be established',
        ]);

        $this->actingAs($admin)->get('/admin/email')
            ->assertOk()
            ->assertSee('recipient@example.com')
            ->assertSee('Connection could not be established');

        Livewire::actingAs($admin)->test(EmailDelivery::class)
            ->set('statusFilter', 'failed')
            ->assertSee('failed@example.com')
            ->assertDontSee('recipient@example.com');
    }

    public function test_the_otp_service_records_a_transport_failure(): void
    {
        // Force the array mailer to fail by pointing mail at an unreachable SMTP host.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        try {
            app(OtpService::class)->issue('someone@example.com', 'login');
            $this->fail('Mail failure was not raised.');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertDatabaseHas('outbound_emails', [
            'to_email' => 'someone@example.com',
            'status' => 'failed',
        ]);
    }

    // -------------------------------------------------------------- heartbeats

    public function test_the_dashboard_reports_heartbeat_ages(): void
    {
        $admin = $this->admin();

        Cache::put('heartbeat:queue', now()->subMinutes(4)->toIso8601String(), 3600);
        Cache::put('heartbeat:schedule', now()->subMinutes(1)->toIso8601String(), 3600);

        Livewire::actingAs($admin)->test(Overview::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['queue_heartbeat_age'] >= 3
                && $stats['queue_heartbeat_age'] <= 6
                && $stats['schedule_heartbeat_age'] <= 2)
            ->assertSee('Queue worker');

        Cache::forget('heartbeat:queue');

        Livewire::actingAs($admin)->test(Overview::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['queue_heartbeat_age'] === null);
    }

    // ------------------------------------------------------------- ops watchdog

    public function test_the_watchdog_alerts_admins_when_the_queue_is_stale(): void
    {
        Mail::fake();
        $admin = $this->admin();
        Cache::put('heartbeat:queue', now()->subHour()->toIso8601String(), 3600);

        $exit = Artisan::call('ops:alert');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Alert sent', Artisan::output());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.alert']);
        $this->assertNotNull(Cache::get('ops:alert:last_sent'));
    }

    public function test_the_watchdog_stays_quiet_when_everything_is_healthy(): void
    {
        Mail::fake();
        $this->admin();
        Cache::put('heartbeat:queue', now()->toIso8601String(), 3600);
        Cache::put('ops:failed_jobs_count', 0, 86400);

        Artisan::call('ops:alert');

        $this->assertStringContainsString('healthy', Artisan::output());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'ops.alert']);
    }

    public function test_the_watchdog_records_its_own_delivery_failures(): void
    {
        $admin = $this->admin();
        Cache::put('heartbeat:queue', now()->subHour()->toIso8601String(), 3600);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        Artisan::call('ops:alert');

        $this->assertDatabaseHas('outbound_emails', [
            'to_email' => $admin->email,
            'status' => 'failed',
        ]);
    }

    // ---------------------------------------------------------- retry guards

    public function test_an_admin_retry_is_refused_while_a_scan_is_already_running(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $client = $this->client();
        $profile = $this->profileFor($client);
        $scan = $this->scanFor($profile, ['status' => 'failed']);
        $this->scanFor($profile, ['status' => 'checking_sources', 'completed_at' => null]);

        Livewire::actingAs($admin)->test(ScanInspector::class, ['scan' => $scan])
            ->call('retry')
            ->assertHasErrors('action');

        $this->assertSame(2, Scan::count());
    }

    public function test_an_admin_retry_without_consent_is_a_form_error(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $client = $this->client();
        $profile = $this->profileFor($client);
        app(ConsentService::class)->revoke($profile);

        $scan = $this->scanFor($profile, ['status' => 'failed']);

        Livewire::actingAs($admin)->test(ScanInspector::class, ['scan' => $scan])
            ->call('retry')
            ->assertHasErrors('action');

        $this->assertSame(1, Scan::count());
    }

    public function test_the_operations_overview_counts_every_active_scan_status(): void
    {
        $admin = $this->admin();
        $profile = $this->profileFor($this->client());
        $this->scanFor($profile, ['status' => 'initializing', 'completed_at' => null]);
        $this->scanFor($profile, ['status' => 'generating_report', 'completed_at' => null]);

        Livewire::actingAs($admin)->test(Overview::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['scans_active'] === 2);
    }
}
