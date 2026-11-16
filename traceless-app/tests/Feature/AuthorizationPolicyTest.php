<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $stranger;

    private IdentityProfile $profile;

    private Scan $scan;

    private RemovalTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->stranger = User::factory()->create();

        $this->profile = IdentityProfile::create([
            'user_id' => $this->owner->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        app(ConsentService::class)->recordConsent($this->owner, $this->profile, '127.0.0.1');

        $this->scan = Scan::create([
            'identity_profile_id' => $this->profile->id,
            'type' => 'snapshot',
            'status' => 'checking_sources',
            'progress_percentage' => 20,
            'current_stage' => 'Checking sources',
            'demo_mode' => false,
        ]);

        $finding = Finding::create([
            'identity_profile_id' => $this->profile->id,
            'scan_id' => $this->scan->id,
            'source' => 'GitHub',
            'category' => 'developer_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'confidence' => 0.9,
            'risk' => 'low',
            'removal_method' => 'manual_instructions',
            'status' => 'needs_user_action',
            'title' => 'Public profile on GitHub',
            'discovered_at' => now(),
            'last_checked_at' => now(),
        ]);

        $this->task = RemovalTask::create([
            'identity_profile_id' => $this->profile->id,
            'finding_id' => $finding->id,
            'source_name' => 'Example',
            'status' => 'ready_to_remove',
            'workflow_type' => 'manual',
            'estimated_turnaround' => '1-7 days',
        ]);
    }

    public function test_the_owner_reaches_their_own_resources(): void
    {
        $this->actingAs($this->owner)->get('/scans/'.$this->scan->id)->assertOk();
        $this->actingAs($this->owner)->get('/removals/'.$this->task->id)->assertOk();
    }

    public function test_a_stranger_is_refused(): void
    {
        $this->actingAs($this->stranger)->get('/scans/'.$this->scan->id)->assertForbidden();
        $this->actingAs($this->stranger)->get('/removals/'.$this->task->id)->assertForbidden();
    }

    /**
     * Proves the components consult the policy layer: a global deny must stop the
     * owner too. An inline user_id comparison would ignore this.
     */
    public function test_access_goes_through_the_gate(): void
    {
        Gate::before(fn () => false);

        $this->actingAs($this->owner)->get('/scans/'.$this->scan->id)->assertForbidden();
        $this->actingAs($this->owner)->get('/removals/'.$this->task->id)->assertForbidden();
        $this->actingAs($this->owner)->get('/findings')->assertOk(); // list has no per-row gate
    }
}
