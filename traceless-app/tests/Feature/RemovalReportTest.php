<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\RemovalTask;
use App\Models\Report;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\RemovalWorkflowService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RemovalReportTest extends TestCase
{
    use RefreshDatabase;

    protected function profileFor(User $user): IdentityProfile
    {
        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
        ]);
        app(ConsentService::class)->recordConsent($user, $profile);

        return $profile;
    }

    protected function findingFor(IdentityProfile $profile, array $overrides = []): Finding
    {
        return Finding::create(array_merge([
            'identity_profile_id' => $profile->id,
            'source' => 'Whitepages',
            'category' => 'data_broker',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'risk' => 'critical',
            'confidence' => 0.9,
            'removal_method' => 'automated_request',
            'status' => 'ready_to_remove',
            'title' => 'Broker listing',
        ], $overrides));
    }

    public function test_removal_status_updates_finding_and_completes_task(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);
        $finding = $this->findingFor($profile);

        $task = RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => $finding->source,
            'status' => 'ready_to_remove',
            'workflow_type' => 'automated',
            'estimated_turnaround' => '3-7 days',
        ]);

        $workflow = app(RemovalWorkflowService::class);

        $workflow->updateStatus($task, 'in_progress');
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->requested_at);
        $this->assertSame('in_progress', $finding->fresh()->status);

        $workflow->updateStatus($task, 'removed', 'Confirmed gone.');
        $this->assertSame('removed', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame('removed', $finding->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'removal_status_updated']);
    }

    public function test_invalid_removal_status_is_rejected(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);
        $finding = $this->findingFor($profile);

        $task = RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => $finding->source,
            'status' => 'ready_to_remove',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(RemovalWorkflowService::class)->updateStatus($task, 'invented_status');
    }

    public function test_a_mail_outage_does_not_block_a_removal_status_update(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $profile = $this->profileFor($user);
        $finding = $this->findingFor($profile);

        $task = RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => $finding->source,
            'status' => 'ready_to_remove',
        ]);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        app(RemovalWorkflowService::class)->updateStatus($task, 'in_progress');

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'removal_status_updated']);
    }

    public function test_signed_report_download_works_and_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);
        $this->findingFor($profile);

        $report = app(ReportService::class)->generate($profile, 'snapshot');

        $url = URL::temporarySignedRoute('reports.download', now()->addMinutes(10), [
            'report' => $report->id,
            'token' => $report->download_token,
        ]);

        $response = $this->actingAs($user)->get($url);
        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertSame(1, $report->fresh()->download_count);

        $other = User::factory()->create();
        $this->actingAs($other)->get($url)->assertForbidden();
    }

    public function test_report_download_rejects_tampered_token(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);
        $report = app(ReportService::class)->generate($profile, 'snapshot');

        $url = URL::temporarySignedRoute('reports.download', now()->addMinutes(10), [
            'report' => $report->id,
            'token' => 'not-the-real-token',
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_unsigned_report_url_is_rejected(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);
        $report = Report::create([
            'identity_profile_id' => $profile->id,
            'title' => 'Test report',
            'report_type' => 'snapshot',
            'privacy_score' => 80,
            'total_findings' => 0,
            'download_token' => 'abc123',
        ]);

        $this->actingAs($user)->get(route('reports.download', ['report' => $report, 'token' => 'abc123']))
            ->assertForbidden();
    }

    public function test_removal_task_pages_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $profile = $this->profileFor($owner);
        $finding = $this->findingFor($profile);
        $task = RemovalTask::create([
            'identity_profile_id' => $profile->id,
            'finding_id' => $finding->id,
            'source_name' => $finding->source,
            'status' => 'ready_to_remove',
        ]);

        $this->actingAs($owner)->get(route('removals.show', $task))->assertOk();

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('removals.show', $task))->assertForbidden();
        $this->actingAs($intruder)->get(route('findings.show', $finding))->assertForbidden();
    }
}
