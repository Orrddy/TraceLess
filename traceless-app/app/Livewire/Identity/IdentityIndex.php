<?php

namespace App\Livewire\Identity;

use App\Models\IdentityProfile;
use App\Models\PublicIdentifier;
use App\Services\RiskScoringService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class IdentityIndex extends Component
{
    public $profiles = [];

    public ?int $editingId = null;

    public string $region = '';

    public string $aliases = '';

    public string $new_identifier = '';

    public string $new_type = 'username';

    public function mount(): void
    {
        $this->profiles = IdentityProfile::where('user_id', Auth::id())->with('publicIdentifiers')->latest()->get();
    }

    public function edit(int $profileId): void
    {
        $profile = $this->owned($profileId);
        $this->editingId = $profile->id;
        $this->region = (string) $profile->region;
        $this->aliases = (string) $profile->aliases;
    }

    public function save(): void
    {
        $profile = $this->owned($this->editingId);
        $this->validate([
            'region' => 'nullable|string|max:160',
            'aliases' => 'nullable|string|max:1000',
        ]);

        $profile->update([
            'region' => strip_tags($this->region) ?: null,
            'aliases' => strip_tags($this->aliases) ?: null,
        ]);

        $this->editingId = null;
        $this->mount();
    }

    public function addIdentifier(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->validate([
            'new_identifier' => 'required|string|max:255',
            'new_type' => 'required|in:email,username,phone,url',
        ]);

        $profile = $this->owned($this->editingId);
        $value = trim($this->new_identifier);
        if ($this->new_type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError('new_identifier', 'Enter a valid email address.');

            return;
        }
        if ($this->new_type === 'url' && ! filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError('new_identifier', 'Enter a valid URL.');

            return;
        }

        PublicIdentifier::firstOrCreate([
            'identity_profile_id' => $profile->id,
            'type' => $this->new_type,
            'value' => $value,
        ], ['is_monitored' => true]);

        $this->new_identifier = '';
        $this->mount();
    }

    public function removeIdentifier(int $identifierId): void
    {
        $identifier = PublicIdentifier::findOrFail($identifierId);
        if ($identifier->identityProfile->user_id !== Auth::id()) {
            abort(403);
        }
        $identifier->delete();
        $this->mount();
    }

    public function regenerateScore(int $profileId, RiskScoringService $scorer): void
    {
        $scorer->recalculateAndPersist($this->owned($profileId)->id);
        $this->mount();
    }

    protected function owned(?int $id): IdentityProfile
    {
        $profile = IdentityProfile::findOrFail($id);
        abort_unless($profile->user_id === Auth::id(), 403);

        return $profile;
    }

    public function render()
    {
        return view('livewire.identity-index')
            ->layout('layouts.app', ['title' => 'My Identity // TraceLess']);
    }
}
