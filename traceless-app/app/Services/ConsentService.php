<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\IdentityProfile;
use App\Models\User;

class ConsentService
{
    public const REQUIRED_CONSENT_TEXT = 'I certify that I am the verified owner of this personal information, or have received explicit legal authorization from the individual to perform a public-data privacy assessment on their behalf.';

    public function recordConsent(User $user, IdentityProfile $profile, ?string $ip = null, ?string $userAgent = null): ConsentRecord
    {
        $profile->update([
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
        ]);

        return ConsentRecord::create([
            'user_id' => $user->id,
            'identity_profile_id' => $profile->id,
            'consent_text' => self::REQUIRED_CONSENT_TEXT,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'agreed_at' => now(),
        ]);
    }

    /**
     * A profile can only be scanned while the owner of the record has a
     * recorded, acknowledged consent event for that exact profile.
     */
    public function hasValidConsent(IdentityProfile $profile): bool
    {
        if (! $profile->consent_acknowledged || ! $profile->consent_given_at) {
            return false;
        }

        return $profile->user()
            ->whereHas('consentRecords', function ($query) use ($profile) {
                $query->where('identity_profile_id', $profile->id);
            })
            ->exists();
    }

    public function revoke(IdentityProfile $profile): void
    {
        $profile->update([
            'consent_acknowledged' => false,
        ]);
    }
}
