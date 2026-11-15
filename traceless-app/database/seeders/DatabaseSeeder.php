<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\BillingRecord;
use App\Models\ConsentRecord;
use App\Models\CredentialExposure;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\MonitoringSubscription;
use App\Models\PublicIdentifier;
use App\Models\RemovalInstruction;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Models\ScanSource;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DemoScanAdapter;
use App\Services\ReportService;
use App\Services\RiskScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRemovalInstructions();

        $user = User::factory()->create([
            'name' => 'Alex Morgan',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(3),
        ]);

        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Alex Morgan',
            'previous_names' => 'Alexandra Morgan',
            'aliases' => 'alexm, alex_morgan, amorgan',
            'primary_email' => 'alex.morgan@example.com',
            'phone_number' => '+49 30 1234 5678',
            'region' => 'Berlin, Germany',
            'consent_acknowledged' => true,
            'consent_given_at' => now()->subDays(21),
            'privacy_score' => 100,
        ]);

        foreach ([
            ['username', 'alexmorgan'],
            ['email', 'alex.morgan@example.com'],
            ['phone', '+49 30 1234 5678'],
            ['url', 'https://alexmorgan.dev'],
        ] as [$type, $value]) {
            PublicIdentifier::create([
                'identity_profile_id' => $profile->id,
                'type' => $type,
                'value' => $value,
                'is_monitored' => true,
            ]);
        }

        ConsentRecord::create([
            'user_id' => $user->id,
            'identity_profile_id' => $profile->id,
            'consent_text' => 'I confirm I am the owner of this identity, or hold explicit written permission to scan it, and that only public sources may be checked. Consent version 1.0.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TraceLess demo seeder',
            'agreed_at' => now()->subDays(21),
        ]);

        $adapter = app(DemoScanAdapter::class);
        $scan = Scan::create([
            'identity_profile_id' => $profile->id,
            'type' => 'deep_scan',
            'status' => 'completed',
            'progress_percentage' => 100,
            'current_stage' => 'Report generated',
            'demo_mode' => true,
            'sources_checked' => count(DemoScanAdapter::SOURCE_CATALOG),
            'sources_total' => count(DemoScanAdapter::SOURCE_CATALOG),
            'findings_count' => 0,
            'started_at' => now()->subHours(2)->subMinutes(4),
            'completed_at' => now()->subHours(2),
        ]);

        $sources = collect(DemoScanAdapter::SOURCE_CATALOG)->take(10);
        foreach ($sources as $index => $source) {
            ScanSource::create([
                'scan_id' => $scan->id,
                'name' => $source['name'],
                'category' => $source['category'],
                'status' => 'completed',
                'findings_found' => $index < 7 ? 1 : 0,
            ]);
        }

        $rawFindings = collect($adapter->findingsFor($profile));

        // Guarantee a representative spread of broker, paste and social records
        // even if the deterministic demo seed omits them for this identity.
        foreach (['Whitepages', 'FastPeopleSearch', 'Spokeo', 'BeenVerified', 'Pastebin archive'] as $required) {
            if ($rawFindings->contains('source', $required)) {
                continue;
            }

            $source = collect(DemoScanAdapter::SOURCE_CATALOG)->firstWhere('name', $required);
            if (! $source) {
                continue;
            }

            $rawFindings->push([
                'source' => $source['name'],
                'category' => $source['category'],
                'identifier_type' => 'username',
                'identifier_value' => 'alexmorgan',
                'url' => str_replace(['{username}', '{name}'], ['alexmorgan', 'alex-morgan'], $source['url']),
                'confidence' => 0.91,
                'risk' => $source['risk'],
                'removal_method' => $source['removal'],
                'status' => $source['removal'] === 'automated_request' ? 'ready_to_remove' : 'needs_user_action',
                'title' => $this->titleFor($source['category']),
                'description' => $this->descriptionFor($source['name'], $source['category']),
                'action_guidance' => $this->guidanceFor($source['category']),
            ]);
        }

        $statusBySource = [
            'Whitepages' => 'removed',
            'FastPeopleSearch' => 'in_progress',
            'Spokeo' => 'waiting_for_source',
            'BeenVerified' => 'ready_to_remove',
            'Pastebin archive' => 'user_action_required',
            'GitHub' => 'needs_user_action',
        ];

        $findings = collect();
        foreach ($rawFindings->values() as $index => $row) {
            $finding = Finding::create([
                'identity_profile_id' => $profile->id,
                'scan_id' => $scan->id,
                'source' => $row['source'],
                'category' => $row['category'],
                'identifier_type' => $row['identifier_type'],
                'identifier_value' => $row['identifier_value'],
                'url' => $row['url'],
                'confidence' => $row['confidence'],
                'risk' => $row['risk'],
                'removal_method' => $row['removal_method'],
                'status' => $statusBySource[$row['source']] ?? $row['status'],
                'title' => $row['title'],
                'description' => $row['description'],
                'action_guidance' => $row['action_guidance'],
                'discovered_at' => now()->subDays(20)->addHours($index * 3),
                'last_checked_at' => now()->subHours(2),
            ]);

            $findings->push($finding);
        }

        $scan->update(['findings_count' => $findings->count()]);

        $removalMeta = [
            'Whitepages' => ['removed', 'automated', '3-7 days', 'https://www.whitepages.com/suppression-requests'],
            'FastPeopleSearch' => ['in_progress', 'automated', '24-48 hours', 'https://www.fastpeoplesearch.com/removal'],
            'Spokeo' => ['waiting_for_source', 'automated', '5-10 days', 'https://www.spokeo.com/optout'],
            'BeenVerified' => ['ready_to_remove', 'automated', '24-72 hours', 'https://www.beenverified.com/app/optout/search'],
            'Pastebin archive' => ['user_action_required', 'manual', '1-5 days', 'https://pastebin.com/contact'],
        ];

        foreach ($findings as $finding) {
            if (! isset($removalMeta[$finding->source])) {
                continue;
            }

            [$status, $workflow, $turnaround, $optOut] = $removalMeta[$finding->source];

            RemovalTask::create([
                'identity_profile_id' => $profile->id,
                'finding_id' => $finding->id,
                'source_name' => $finding->source,
                'status' => $status,
                'workflow_type' => $workflow,
                'estimated_turnaround' => $turnaround,
                'opt_out_url' => $optOut,
                'notes' => match ($status) {
                    'removed' => 'Opt-out confirmed by source on '.now()->subDay()->format('d M Y').'. Record no longer resolves.',
                    'in_progress' => 'Request submitted. Awaiting identity verification email.',
                    'waiting_for_source' => 'Source acknowledged the request; processing window open.',
                    'user_action_required' => 'Source requires manual review request with the specific paste URL.',
                    default => null,
                },
                'requested_at' => $status === 'ready_to_remove' ? null : now()->subDays(6),
                'completed_at' => $status === 'removed' ? now()->subDay() : null,
            ]);
        }

        foreach ($adapter->credentialExposuresFor($profile) as $credential) {
            CredentialExposure::create([
                'identity_profile_id' => $profile->id,
                'email' => $credential['email'],
                'service_name' => $credential['service_name'],
                'breach_date' => $credential['breach_date'],
                'exposed_data_classes' => $credential['exposed_data_classes'],
                'risk_level' => $credential['risk_level'],
                'is_resolved' => false,
            ]);
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'deep_scan',
            'status' => 'active',
            'provider' => 'demo',
            'provider_reference' => 'demo_sub_'.Str::random(12),
            'current_period_end' => now()->addWeeks(3),
        ]);

        BillingRecord::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'amount_cents' => 2400,
            'currency' => 'USD',
            'status' => 'paid',
            'description' => 'Deep Scan — monthly (demo)',
            'provider_reference' => 'demo_inv_'.Str::random(10),
            'paid_at' => now()->subWeek(),
        ]);

        MonitoringSubscription::create([
            'identity_profile_id' => $profile->id,
            'is_active' => true,
            'cadence' => 'weekly',
            'options' => [
                'new_profiles' => true,
                'contact_info' => true,
                'data_brokers' => true,
                'reappearing' => true,
                'credentials' => true,
                'impersonation' => false,
            ],
            'last_scanned_at' => now()->subDays(5),
            'next_scan_at' => now()->addDays(2),
        ]);

        Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'new_exposure',
            'severity' => 'high',
            'title' => 'New broker listing detected',
            'message' => 'FastPeopleSearch published a new record containing your phone number and Berlin region.',
            'action_url' => '/removals',
            'is_read' => false,
        ]);

        Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'credential_leak',
            'severity' => 'high',
            'title' => 'Credential exposure requires action',
            'message' => 'Your email appears in a public breach archive. Rotate the password on any service that still uses it.',
            'action_url' => '/credentials',
            'is_read' => false,
        ]);

        Alert::create([
            'identity_profile_id' => $profile->id,
            'type' => 'reappeared',
            'severity' => 'critical',
            'title' => 'A removed record reappeared',
            'message' => 'A paste archive re-indexed a mention of @alexmorgan after removal. Re-submit the request and document it.',
            'action_url' => '/removals',
            'is_read' => true,
            'read_at' => now()->subHours(8),
        ]);

        app(RiskScoringService::class)->recalculateAndPersist($profile->id);

        $report = app(ReportService::class)->generate($profile->fresh(), 'snapshot');
        $report->update(['download_count' => 1]);
    }

    protected function seedRemovalInstructions(): void
    {
        $instructions = [
            [
                'source_identifier' => 'whitepages',
                'title' => 'Whitepages suppression request',
                'step_1' => 'Open the Whitepages suppression centre and search for the exact record URL.',
                'step_2' => 'Verify your identity through the email or phone challenge. Never send a password.',
                'step_3' => 'Submit the suppression request and save the confirmation reference.',
                'step_4' => 'Recheck the listing after 7 days and escalate if it remains indexed.',
                'direct_opt_out_link' => 'https://www.whitepages.com/suppression-requests',
                'typical_turnaround' => '3-7 days',
            ],
            [
                'source_identifier' => 'fastpeoplesearch',
                'title' => 'FastPeopleSearch removal',
                'step_1' => 'Locate the record and copy its detail page URL.',
                'step_2' => 'Submit the official removal form with the URL and a contact email.',
                'step_3' => 'Complete the emailed confirmation link.',
                'step_4' => 'Verify the record is gone after 48 hours; repeat if it reappears.',
                'direct_opt_out_link' => 'https://www.fastpeoplesearch.com/removal',
                'typical_turnaround' => '24-48 hours',
            ],
            [
                'source_identifier' => 'spokeo',
                'title' => 'Spokeo opt-out',
                'step_1' => 'Search Spokeo for your listing and copy the profile URL.',
                'step_2' => 'Open the opt-out page and paste the URL.',
                'step_3' => 'Confirm the verification email sent to your address.',
                'step_4' => 'Allow the stated processing window, then recheck.',
                'direct_opt_out_link' => 'https://www.spokeo.com/optout',
                'typical_turnaround' => '5-10 days',
            ],
            [
                'source_identifier' => 'beenverified',
                'title' => 'BeenVerified opt-out',
                'step_1' => 'Find the matching record and copy its URL.',
                'step_2' => 'Use the opt-out search tool and select the correct listing.',
                'step_3' => 'Confirm via the verification email. Do not pay for removal.',
                'step_4' => 'Recheck after 72 hours and keep the confirmation.',
                'direct_opt_out_link' => 'https://www.beenverified.com/app/optout/search',
                'typical_turnaround' => '24-72 hours',
            ],
            [
                'source_identifier' => 'pastebin-archive',
                'title' => 'Paste archive removal',
                'step_1' => 'Open the paste URL and record its identifier.',
                'step_2' => 'Contact the paste host with the URL and explain the personal-data concern.',
                'step_3' => 'Rotate any credential that may have appeared in the archive.',
                'step_4' => 'Request search-engine de-indexing if the cache persists.',
                'direct_opt_out_link' => 'https://pastebin.com/contact',
                'typical_turnaround' => '1-5 days',
            ],
        ];

        foreach ($instructions as $instruction) {
            RemovalInstruction::updateOrCreate(
                ['source_identifier' => $instruction['source_identifier']],
                $instruction
            );
        }
    }

    protected function titleFor(string $category): string
    {
        return match ($category) {
            'data_broker' => 'Public data broker listing',
            'public_paste' => 'Archived paste mentioning your handle',
            'developer_profile' => 'Public developer profile',
            'public_avatar' => 'Public avatar and email hash',
            'public_directory' => 'Directory listing with contact metadata',
            default => 'Public social profile',
        };
    }

    protected function descriptionFor(string $source, string $category): string
    {
        return match ($category) {
            'data_broker' => "{$source} exposes an aggregated record containing contact details and location history derived from public sources.",
            'public_paste' => 'A public paste archive contains your handle. The record may include metadata that can be correlated with your identity.',
            'developer_profile' => "{$source} publishes a discoverable profile, contribution graph and possibly a public email address.",
            'public_avatar' => 'A public avatar service exposes an email-derived hash and profile metadata.',
            default => "{$source} has a public profile reachable from your handle. It may expose activity patterns and linked accounts.",
        };
    }

    protected function guidanceFor(string $category): string
    {
        return match ($category) {
            'data_broker' => 'Use the source opt-out form. Expect identity verification and a 3-14 day processing window. Follow up if the record reappears.',
            'public_paste' => 'Review the paste for exposed identifiers. Request removal from the paste host and rotate any credential referenced by the archive.',
            'developer_profile' => 'Set your profile email to private, review pinned repositories and remove location or employer metadata you do not want indexed.',
            'public_avatar' => 'Remove the public avatar association or replace the email hash with a non-identifying image.',
            default => 'Review the platform privacy settings. Opt out of search engine indexing and restrict profile visibility to connections.',
        };
    }
}
