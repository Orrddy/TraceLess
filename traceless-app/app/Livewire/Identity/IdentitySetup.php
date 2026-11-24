<?php

namespace App\Livewire\Identity;

use App\Models\IdentityProfile;
use App\Models\PublicIdentifier;
use App\Services\AuditService;
use App\Services\ConsentService;
use App\Services\IpReputationService;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

class IdentitySetup extends Component
{
    /** Every name the person is known by. The first becomes the primary name. */
    public array $names = [];

    public string $name_input = '';

    public string $primary_email = '';

    public string $phone_number = '';

    public string $username_input = '';

    /** @var array<int, string> */
    public array $usernames = [];

    public bool $consent_confirmed = false;

    public bool $start_scan = true;

    /** Set by the "Get my IP" helper, so the form can hand off to the IP check. */
    public ?string $detected_ip = null;

    public ?string $ip_error = null;

    public function addName(): void
    {
        $value = trim(strip_tags($this->name_input));

        if ($value === '') {
            return;
        }

        if (in_array($value, $this->names, true)) {
            $this->addError('name_input', 'That name is already on the list.');

            return;
        }

        $this->names[] = $value;
        $this->name_input = '';
        $this->resetErrorBag('name_input');
    }

    public function removeName(int $index): void
    {
        if (isset($this->names[$index])) {
            unset($this->names[$index]);
            $this->names = array_values($this->names);
        }
    }

    public function addUsername(): void
    {
        $value = ltrim(trim($this->username_input), '@');

        if ($value === '') {
            return;
        }

        if (in_array($value, $this->usernames, true)) {
            $this->addError('username_input', 'That username is already on the list.');

            return;
        }

        $this->usernames[] = $value;
        $this->username_input = '';
        $this->resetErrorBag('username_input');
    }

    public function removeUsername(int $index): void
    {
        if (isset($this->usernames[$index])) {
            unset($this->usernames[$index]);
            $this->usernames = array_values($this->usernames);
        }
    }

    /**
     * "Get my IP": resolves the public address this network reaches the internet
     * with, so the person can hand it straight to the IP check.
     */
    public function detectIp(IpReputationService $lookup): void
    {
        $this->ip_error = null;

        try {
            $result = $lookup->lookup();
            $this->detected_ip = $result['ip'] ?? null;
        } catch (Throwable $exception) {
            $this->ip_error = 'Could not read the public address. Run the IP check instead.';
        }
    }

    public function saveAndScan(ConsentService $consentService, ScanOrchestrator $orchestrator, AuditService $audit)
    {
        $this->validate([
            'names' => 'required|array|min:1',
            'names.*' => 'nullable|string|max:160',
            'primary_email' => 'required|email|max:255',
            'phone_number' => 'nullable|string|max:40',
            'usernames' => 'array',
            'usernames.*' => 'nullable|string|max:120',
            'consent_confirmed' => 'accepted',
        ]);

        $names = collect($this->names)
            ->map(fn ($name) => trim(strip_tags((string) $name)))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            $this->addError('names', 'Add at least one name before saving.');

            return;
        }

        $user = Auth::user();

        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => $names->first(),
            'previous_names' => $names->slice(1)->implode(', ') ?: null,
            'primary_email' => $this->primary_email,
            'phone_number' => $this->phone_number ?: null,
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
            'privacy_score' => 100,
        ]);

        $this->storeIdentifier($profile, 'email', $this->primary_email);

        foreach (array_unique($this->usernames) as $username) {
            $this->storeIdentifier($profile, 'username', ltrim((string) $username, '@'));
        }

        if ($this->phone_number) {
            $this->storeIdentifier($profile, 'phone', $this->phone_number);
        }

        $consentService->recordConsent($user, $profile, request()->ip(), request()->userAgent());
        $audit->log($user->id, 'identity_created', "Identity profile #{$profile->id} created with consent.", request()->ip());

        if (! $this->start_scan) {
            return redirect()->route('identity')->with('status', 'Identity saved. You can run a scan whenever you are ready.');
        }

        $scan = $orchestrator->queueScan($profile, 'snapshot');

        return redirect()->route('scans.show', $scan);
    }

    protected function storeIdentifier(IdentityProfile $profile, string $type, string $value): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        PublicIdentifier::firstOrCreate([
            'identity_profile_id' => $profile->id,
            'type' => $type,
            'value' => $value,
        ], ['is_monitored' => true]);
    }

    public function render()
    {
        return view('livewire.identity-setup')
            ->layout('layouts.app', ['title' => 'Identity setup — TraceLess']);
    }
}
