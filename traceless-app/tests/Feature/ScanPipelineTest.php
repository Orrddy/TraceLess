<?php

namespace Tests\Feature;

use App\Jobs\RunScanJob;
use App\Livewire\Scans\ScanCreate;
use App\Livewire\Scans\ScanProgress;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\DemoScanAdapter;
use App\Services\ScanOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ScanPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProfile(User $user, bool $consent = true): IdentityProfile
    {
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'privacy_score' => 100,
        ]);

        $profile->publicIdentifiers()->create([
            'type' => 'username',
            'value' => 'janedoe',
            'is_monitored' => true,
        ]);

        if ($consent) {
            app(ConsentService::class)->recordConsent($user, $profile, '127.0.0.1');
        }

        return $profile;
    }

    public function test_queue_scan_refuses_without_consent(): void
    {
        $user = User::factory()->create();
        $profile = $this->makeProfile($user, false);

        $this->expectException(RuntimeException::class);

        app(ScanOrchestrator::class)->queueScan($profile);
    }

    public function test_queue_scan_dispatches_job_and_creates_scan(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $profile = $this->makeProfile($user);

        $scan = app(ScanOrchestrator::class)->queueScan($profile, 'snapshot');

        $this->assertSame('queued', $scan->status);
        $this->assertSame($profile->id, $scan->identity_profile_id);
        $this->assertTrue($scan->demo_mode);
        Queue::assertPushedOn('scans', RunScanJob::class);
    }

    public function test_demo_scan_executes_end_to_end_and_generates_report(): void
    {
        Queue::fake();
        config(['traceless.demo_mode' => true, 'traceless.scan.stage_delay_ms' => 0]);

        $user = User::factory()->create();
        $profile = $this->makeProfile($user);

        $orchestrator = app(ScanOrchestrator::class);
        $scan = $orchestrator->queueScan($profile, 'snapshot');
        $orchestrator->executeScan($scan);

        $scan->refresh();
        $this->assertSame('completed', $scan->status);
        $this->assertSame(100, $scan->progress_percentage);
        $this->assertGreaterThan(0, $scan->findings_count);
        $this->assertGreaterThan(0, Finding::where('scan_id', $scan->id)->count());
        $this->assertDatabaseHas('reports', ['identity_profile_id' => $profile->id]);
        $this->assertLessThan(100, $profile->fresh()->privacy_score);
        $this->assertDatabaseCount('scan_sources', count(DemoScanAdapter::SOURCE_CATALOG) + 1);
    }

    public function test_scan_can_be_cancelled_and_is_owned(): void
    {
        $user = User::factory()->create();
        $profile = $this->makeProfile($user);
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'snapshot',
            'status' => 'checking_sources',
            'progress_percentage' => 20,
            'current_stage' => 'Working',
        ]);

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('scans.show', $scan))->assertForbidden();

        $this->actingAs($user);
        Livewire::test(ScanProgress::class, ['scan' => $scan])
            ->call('cancel');

        $this->assertSame('cancelled', $scan->fresh()->status);
        $this->assertNotNull($scan->fresh()->cancelled_at);

        $this->get(route('scans.show', $scan))->assertOk();
    }

    public function test_scan_creation_component_queues_a_scan(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $profile = $this->makeProfile($user);
        $this->actingAs($user);

        Livewire::test(ScanCreate::class)
            ->set('profile_id', $profile->id)
            ->set('type', 'snapshot')
            ->set('consent_confirmed', true)
            ->call('start')
            ->assertRedirect();

        $this->assertDatabaseCount('scans', 1);
        Queue::assertPushed(RunScanJob::class);
    }

    public function test_deep_scan_requires_an_active_plan(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $profile = $this->makeProfile($user);
        $this->actingAs($user);

        Livewire::test(ScanCreate::class)
            ->set('profile_id', $profile->id)
            ->set('type', 'deep_scan')
            ->set('consent_confirmed', true)
            ->call('start')
            ->assertHasErrors('type');

        $this->assertDatabaseCount('scans', 0);
    }

    public function test_deep_scan_plan_allows_deep_scan(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'deep_scan',
            'status' => 'active',
            'provider' => 'demo',
        ]);
        $profile = $this->makeProfile($user);
        $this->actingAs($user);

        Livewire::test(ScanCreate::class)
            ->set('profile_id', $profile->id)
            ->set('type', 'deep_scan')
            ->set('consent_confirmed', true)
            ->call('start')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scans', ['type' => 'deep_scan']);
    }
}
