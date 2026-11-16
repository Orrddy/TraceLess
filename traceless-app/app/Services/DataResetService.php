<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\CredentialExposure;
use App\Models\Finding;
use App\Models\MonitoringSubscription;
use App\Models\RemovalTask;
use App\Models\Report;
use App\Models\Scan;
use App\Models\ScanSource;
use Illuminate\Support\Facades\DB;

/**
 * Clears everything the scanner produced for one identity.
 *
 * The identity, its identifiers and its consent record are kept, so the user can
 * immediately run a fresh scan without re-onboarding. Consent is deliberately not
 * touched: it is the gate that authorises scanning, not scan output.
 */
class DataResetService
{
    /**
     * @return array{scans: int, findings: int, removals: int, reports: int, alerts: int}
     */
    public function purgeProfileData(int $profileId): array
    {
        return DB::transaction(function () use ($profileId) {
            $scanIds = Scan::where('identity_profile_id', $profileId)->pluck('id');

            $counts = [
                'scans' => $scanIds->count(),
                'findings' => Finding::where('identity_profile_id', $profileId)->count(),
                'removals' => RemovalTask::where('identity_profile_id', $profileId)->count(),
                'reports' => Report::where('identity_profile_id', $profileId)->count(),
                'alerts' => Alert::where('identity_profile_id', $profileId)->count(),
            ];

            // Children before parents: removal tasks point at findings, findings
            // and sources point at scans.
            RemovalTask::where('identity_profile_id', $profileId)->delete();
            Finding::where('identity_profile_id', $profileId)->delete();
            ScanSource::whereIn('scan_id', $scanIds)->delete();
            Scan::whereIn('id', $scanIds)->delete();
            Report::where('identity_profile_id', $profileId)->delete();
            CredentialExposure::where('identity_profile_id', $profileId)->delete();
            Alert::where('identity_profile_id', $profileId)->delete();
            MonitoringSubscription::where('identity_profile_id', $profileId)->delete();

            return $counts;
        });
    }
}
