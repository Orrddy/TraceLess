<?php

namespace App\Services;

use App\Models\IdentityProfile;
use App\Models\Scan;

/**
 * Deterministic local development adapter.
 *
 * This is NOT a live scanner. It produces realistic, repeatable demo data so
 * the entire product can be exercised without outbound requests. Every scan
 * created while demo mode is on is flagged in the database, UI and reports.
 */
class DemoScanAdapter
{
    public const SOURCE_CATALOG = [
        ['name' => 'GitHub', 'category' => 'developer_profile', 'risk' => 'moderate', 'removal' => 'manual_instructions', 'url' => 'https://github.com/{username}'],
        ['name' => 'Reddit', 'category' => 'social_profile', 'risk' => 'low', 'removal' => 'manual_instructions', 'url' => 'https://reddit.com/user/{username}'],
        ['name' => 'X / Twitter', 'category' => 'social_profile', 'risk' => 'moderate', 'removal' => 'manual_instructions', 'url' => 'https://x.com/{username}'],
        ['name' => 'Instagram', 'category' => 'social_profile', 'risk' => 'moderate', 'removal' => 'manual_instructions', 'url' => 'https://instagram.com/{username}'],
        ['name' => 'LinkedIn', 'category' => 'social_profile', 'risk' => 'moderate', 'removal' => 'manual_instructions', 'url' => 'https://linkedin.com/in/{username}'],
        ['name' => 'Stack Overflow', 'category' => 'developer_profile', 'risk' => 'low', 'removal' => 'manual_instructions', 'url' => 'https://stackoverflow.com/users/{username}'],
        ['name' => 'Gravatar', 'category' => 'public_avatar', 'risk' => 'moderate', 'removal' => 'manual_instructions', 'url' => 'https://gravatar.com/{username}'],
        ['name' => 'FastPeopleSearch', 'category' => 'data_broker', 'risk' => 'high', 'removal' => 'automated_request', 'url' => 'https://fastpeoplesearch.com/name/{name}'],
        ['name' => 'Spokeo', 'category' => 'data_broker', 'risk' => 'high', 'removal' => 'automated_request', 'url' => 'https://spokeo.com/search?q={name}'],
        ['name' => 'Whitepages', 'category' => 'data_broker', 'risk' => 'critical', 'removal' => 'automated_request', 'url' => 'https://whitepages.com/name/{name}'],
        ['name' => 'BeenVerified', 'category' => 'data_broker', 'risk' => 'high', 'removal' => 'automated_request', 'url' => 'https://beenverified.com/name/{name}'],
        ['name' => 'Pastebin archive', 'category' => 'public_paste', 'risk' => 'critical', 'removal' => 'manual_instructions', 'url' => 'https://pastebin.com/{username}'],
        ['name' => 'Public directory index', 'category' => 'public_directory', 'risk' => 'moderate', 'removal' => 'manual_instructions', 'url' => 'https://example-directory.org/people/{name}'],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findingsFor(IdentityProfile $profile): array
    {
        $username = $profile->publicIdentifiers
            ->firstWhere('type', 'username')?->value
            ?? str($profile->full_name)->slug('')->toString();

        $slugName = str($profile->full_name)->slug('-')->toString();
        $seed = crc32(strtolower($profile->full_name.$profile->primary_email));

        $findings = [];
        foreach (self::SOURCE_CATALOG as $index => $source) {
            $exists = (($seed >> ($index % 24)) & 1) === 1;

            if ($index < 4) {
                $exists = true; // always surface a few meaningful demo records
            }

            if (! $exists) {
                continue;
            }

            $confidence = round(0.68 + ((($seed >> ($index + 3)) & 15) / 100), 2);
            $url = str_replace(
                ['{username}', '{name}'],
                [$username, $slugName],
                $source['url']
            );

            $findings[] = [
                'source' => $source['name'],
                'category' => $source['category'],
                'identifier_type' => 'username',
                'identifier_value' => $username,
                'url' => $url,
                'confidence' => min(0.98, $confidence),
                'risk' => $source['risk'],
                'removal_method' => $source['removal'],
                'status' => $source['removal'] === 'automated_request' ? 'ready_to_remove' : 'needs_user_action',
                'title' => $this->titleFor($source['category']),
                'description' => $this->descriptionFor($source['name'], $source['category']),
                'action_guidance' => $this->guidanceFor($source['category']),
                'demo' => true,
            ];
        }

        return $findings;
    }

    /**
     * Public breach metadata only. Never passwords or password hashes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function credentialExposuresFor(IdentityProfile $profile): array
    {
        if (! $profile->primary_email) {
            return [];
        }

        $seed = crc32(strtolower($profile->primary_email));
        $catalog = [
            ['service' => 'Collection #1 aggregate', 'date' => '2019-01-07', 'classes' => 'Email addresses, passwords (hashed)', 'risk' => 'high'],
            ['service' => 'Marketing CRM leak', 'date' => '2023-06-18', 'classes' => 'Email addresses, names, company', 'risk' => 'moderate'],
            ['service' => 'Forum backup archive', 'date' => '2024-11-02', 'classes' => 'Email addresses, usernames, IP addresses', 'risk' => 'high'],
        ];

        $out = [];
        foreach ($catalog as $i => $row) {
            if ((($seed >> ($i + 5)) & 1) === 1 || $i === 0) {
                $out[] = [
                    'email' => $profile->primary_email,
                    'service_name' => $row['service'],
                    'breach_date' => $row['date'],
                    'exposed_data_classes' => $row['classes'],
                    'risk_level' => $row['risk'],
                    'is_resolved' => false,
                ];
            }
        }

        return $out;
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
            'data_broker' => 'Use the source opt-out form. Expect identity verification and a 3–14 day processing window. Follow up if the record reappears.',
            'public_paste' => 'Review the paste for exposed identifiers. Request removal from the paste host and rotate any credential referenced by the archive.',
            'developer_profile' => 'Set your profile email to private, review pinned repositories and remove location or employer metadata you do not want indexed.',
            'public_avatar' => 'Remove the public avatar association or replace the email hash with a non-identifying image.',
            default => 'Review the platform privacy settings. Opt out of search engine indexing and restrict profile visibility to connections.',
        };
    }
}
