<?php

namespace App\Livewire;

use App\Services\PythonScannerClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Throwable;

/**
 * A deliberately small, guest-only check.
 *
 * It runs ten curated public-profile checks for one username. Nothing is
 * persisted: the name, email and username live only in this Livewire component
 * (and the name/email are put in the session only to prefill signup). A full
 * scan still requires an account and recorded profile consent.
 */
class QuickScan extends Component
{
    public string $name = '';

    public string $email = '';

    public string $username = '';

    public bool $consent = false;

    /** Honeypot: hidden from humans, filled by basic bots. */
    public string $website = '';

    public bool $scanned = false;

    public string $scanError = '';

    /** @var array<string, mixed>|null */
    public ?array $results = null;

    /** @var list<string> */
    public array $checks = [
        'GitHub',
        'GitLab',
        'Reddit',
        'X',
        'Instagram',
        'Stack Overflow',
        'Gravatar',
        'Medium',
        'DeviantArt',
        'Spotify',
    ];

    public function scan(PythonScannerClient $scanner): void
    {
        $this->resetErrorBag();
        $this->scanError = '';
        $this->scanned = false;
        $this->results = null;

        if ($this->website !== '') {
            $this->scanError = 'The quick scan could not run. Please refresh and try again.';

            return;
        }

        $this->name = trim(strip_tags($this->name));
        $this->email = trim($this->email);
        $this->username = ltrim(trim($this->username), '@');

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Confirm this is your public username before running the check.',
            'username.regex' => 'Use a public username (letters, numbers, dots, dashes and underscores only).',
        ]);

        $rateKey = 'quick-scan:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 2)) {
            $this->addError('username', 'Please wait a few minutes before running another quick check.');

            return;
        }

        RateLimiter::hit($rateKey, 600);

        try {
            $report = $scanner->scanReport(
                'username',
                $this->username,
                true,
                'curated',
                45,
                10,
            );
        } catch (Throwable $exception) {
            Log::warning('guest_quick_scan_failed', [
                'reason' => get_class($exception),
            ]);

            $this->scanError = 'The quick scan could not reach its public sources just now. Please try again in a minute, or create a free account for the full scan.';

            return;
        }

        $findings = collect($report['findings'])->keyBy('source');

        $sources = collect($report['sources'])
            ->map(function (array $source) use ($findings): array {
                $finding = $findings->get($source['name']);
                $outcome = in_array($source['outcome'] ?? null, ['found', 'not_found', 'unknown', 'skipped'], true)
                    ? $source['outcome']
                    : 'unknown';

                return [
                    'name' => (string) $source['name'],
                    'outcome' => $outcome,
                    'reason' => $source['reason'] ?? null,
                    'url' => $finding['url'] ?? ($source['checked_url'] ?? null),
                    'title' => $finding['title'] ?? null,
                ];
            })
            ->values()
            ->all();

        $publicMatches = collect($sources)->where('outcome', 'found')->count();
        $checked = count($sources);

        $this->results = [
            'username' => $this->username,
            'sources' => $sources,
            'public_matches' => $publicMatches,
            'checked' => $checked,
            'unverified' => collect($sources)->whereIn('outcome', ['unknown', 'skipped'])->count(),
            'scanned_at' => now()->format('H:i'),
        ];
        $this->scanned = true;
        $this->dispatch('quick-scan-complete', publicMatches: $publicMatches);

        // Purposeful use of the optional details: the auth pages may prefill,
        // but nothing is written to the database until the person signs up.
        session()->put('quick_scan_prefill', [
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
        ]);
    }

    public function resetScan(): void
    {
        $this->dispatch('quick-scan-reset');
        $this->scanned = false;
        $this->results = null;
        $this->scanError = '';
        $this->username = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.quick-scan')
            ->layout('layouts.guest-scan', ['title' => 'Free quick scan — TraceLess']);
    }
}
