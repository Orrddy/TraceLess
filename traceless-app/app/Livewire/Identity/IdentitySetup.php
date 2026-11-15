<?php

namespace App\Livewire\Identity;

use App\Models\IdentityProfile;
use App\Models\PublicIdentifier;
use App\Services\AuditService;
use App\Services\ConsentService;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class IdentitySetup extends Component
{
    public string $full_name = '';

    public string $previous_names = '';

    public string $aliases = '';

    public string $primary_email = '';

    public string $phone_number = '';

    public string $region = '';

    public string $username_input = '';

    public string $email_input = '';

    public string $phone_input = '';

    public string $url_input = '';

    public array $usernames = [];

    public array $emails = [];

    public array $phones = [];

    public array $urls = [];

    public bool $consent_confirmed = false;

    public bool $start_scan = true;

    public function addUsername(): void
    {
        $this->addChip('username_input', 'usernames', '@');
    }

    public function addEmail(): void
    {
        if (! filter_var(trim($this->email_input), FILTER_VALIDATE_EMAIL)) {
            $this->addError('email_input', 'Enter a valid email address.');

            return;
        }
        $this->addChip('email_input', 'emails');
    }

    public function addPhone(): void
    {
        $this->addChip('phone_input', 'phones');
    }

    public function addUrl(): void
    {
        if (! filter_var(trim($this->url_input), FILTER_VALIDATE_URL)) {
            $this->addError('url_input', 'Enter a valid public profile URL.');

            return;
        }
        $this->addChip('url_input', 'urls');
    }

    protected function addChip(string $input, string $bucket, string $prefix = ''): void
    {
        $value = trim($this->{$input});
        if ($value !== '' && ! in_array($value, $this->{$bucket}, true)) {
            $this->{$bucket}[] = $prefix.$value;
            $this->{$input} = '';
            $this->resetErrorBag($input);
        }
    }

    public function removeChip(string $bucket, int $index): void
    {
        if (isset($this->{$bucket}[$index])) {
            unset($this->{$bucket}[$index]);
            $this->{$bucket} = array_values($this->{$bucket});
        }
    }

    public function saveAndScan(ConsentService $consentService, ScanOrchestrator $orchestrator, AuditService $audit)
    {
        $this->validate([
            'full_name' => 'required|string|min:2|max:160',
            'primary_email' => 'required|email|max:255',
            'phone_number' => 'nullable|string|max:40',
            'region' => 'nullable|string|max:160',
            'previous_names' => 'nullable|string|max:1000',
            'aliases' => 'nullable|string|max:1000',
            'consent_confirmed' => 'accepted',
        ]);

        $user = Auth::user();

        $profile = IdentityProfile::create([
            'user_id' => $user->id,
            'full_name' => strip_tags($this->full_name),
            'previous_names' => strip_tags($this->previous_names) ?: null,
            'aliases' => strip_tags($this->aliases) ?: null,
            'primary_email' => $this->primary_email,
            'phone_number' => $this->phone_number ?: null,
            'region' => strip_tags($this->region) ?: null,
            'consent_acknowledged' => true,
            'consent_given_at' => now(),
            'privacy_score' => 100,
        ]);

        $this->storeIdentifier($profile, 'email', $this->primary_email);
        foreach (array_unique($this->emails) as $email) {
            $this->storeIdentifier($profile, 'email', ltrim($email, '@'));
        }
        foreach (array_unique(array_merge($this->usernames)) as $username) {
            $this->storeIdentifier($profile, 'username', ltrim($username, '@'));
        }
        if ($this->phone_number) {
            $this->storeIdentifier($profile, 'phone', $this->phone_number);
        }
        foreach (array_unique($this->phones) as $phone) {
            $this->storeIdentifier($profile, 'phone', $phone);
        }
        foreach (array_unique($this->urls) as $url) {
            $this->storeIdentifier($profile, 'url', $url);
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
            ->layout('layouts.app', ['title' => 'New Identity // TraceLess']);
    }
}
