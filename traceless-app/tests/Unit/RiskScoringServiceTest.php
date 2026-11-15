<?php

namespace Tests\Unit;

use App\Models\CredentialExposure;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\MonitoringSubscription;
use App\Models\User;
use App\Services\RiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function profile(): IdentityProfile
    {
        $user = User::factory()->create();

        return IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'primary_email' => 'jane@example.com',
        ]);
    }

    protected function finding(IdentityProfile $profile, array $attributes): Finding
    {
        return Finding::create(array_merge([
            'identity_profile_id' => $profile->id,
            'source' => 'Example',
            'category' => 'social_profile',
            'identifier_type' => 'username',
            'identifier_value' => 'janedoe',
            'risk' => 'low',
            'confidence' => 0.9,
            'status' => 'needs_user_action',
            'title' => 'Public profile',
        ], $attributes));
    }

    public function test_clean_profile_scores_near_one_hundred(): void
    {
        $profile = $this->profile();

        $result = app(RiskScoringService::class)->calculate($profile->id);

        $this->assertSame(100, $result['privacy_score']);
        $this->assertSame(0, $result['active_findings']);
        $this->assertSame('Optimal', $result['exposure_level']);
    }

    public function test_active_findings_reduce_score_by_severity(): void
    {
        $profile = $this->profile();
        $this->finding($profile, ['risk' => 'low', 'source' => 'Low']);
        $this->finding($profile, ['risk' => 'critical', 'source' => 'Critical']);

        $result = app(RiskScoringService::class)->calculate($profile->id);

        $this->assertLessThan(100, $result['privacy_score']);
        $this->assertSame(1, $result['critical_count']);
        $this->assertSame(1, $result['low_count']);
        $this->assertSame(2, $result['total_findings']);
    }

    public function test_removed_findings_do_not_count_as_active_and_add_recovery(): void
    {
        $active = $this->profile();
        $this->finding($active, ['risk' => 'critical', 'source' => 'Active']);

        $removed = $this->profile();
        $this->finding($removed, ['risk' => 'critical', 'source' => 'Removed', 'status' => 'removed']);

        $scorer = app(RiskScoringService::class);
        $activeScore = $scorer->calculate($active->id)['privacy_score'];
        $removedScore = $scorer->calculate($removed->id)['privacy_score'];

        $this->assertGreaterThan($activeScore, $removedScore);
        $this->assertSame(1, $scorer->calculate($removed->id)['removed_findings']);
    }

    public function test_unresolved_credentials_and_monitoring_affect_score(): void
    {
        $profile = $this->profile();
        $this->finding($profile, ['risk' => 'moderate', 'source' => 'Social']);

        $scorer = app(RiskScoringService::class);
        $before = $scorer->calculate($profile->id)['privacy_score'];

        CredentialExposure::create([
            'identity_profile_id' => $profile->id,
            'email' => 'jane@example.com',
            'service_name' => 'Breach',
            'risk_level' => 'high',
            'is_resolved' => false,
        ]);

        $afterCredential = $scorer->calculate($profile->id)['privacy_score'];
        $this->assertLessThan($before, $afterCredential);

        MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
        ]);

        $afterMonitoring = $scorer->calculate($profile->id)['privacy_score'];
        $this->assertGreaterThan($afterCredential, $afterMonitoring);
    }

    public function test_score_is_clamped_between_minimum_and_one_hundred(): void
    {
        $profile = $this->profile();

        for ($i = 0; $i < 40; $i++) {
            $this->finding($profile, ['risk' => 'critical', 'source' => 'Source '.$i, 'confidence' => 1]);
        }

        $score = app(RiskScoringService::class)->calculate($profile->id)['privacy_score'];

        $this->assertGreaterThanOrEqual(5, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
}
