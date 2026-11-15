<?php

namespace App\Livewire\Credentials;

use App\Models\CredentialExposure;
use App\Models\IdentityProfile;
use App\Services\RiskScoringService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CredentialExposures extends Component
{
    public ?IdentityProfile $profile = null;

    public string $filter = 'open';

    public bool $demoMode = false;

    public ?string $provider = null;

    public function mount(): void
    {
        $this->profile = IdentityProfile::where('user_id', Auth::id())->latest()->first();
        $this->demoMode = (bool) config('traceless.demo_mode');
        $this->provider = config('traceless.breach.provider');
    }

    public function markResolved(int $id): void
    {
        $cred = CredentialExposure::find($id);
        if ($cred && $cred->identity_profile_id === $this->profile?->id) {
            $cred->update(['is_resolved' => true, 'resolved_at' => now()]);
            app(RiskScoringService::class)->recalculateAndPersist($this->profile->id);
        }
    }

    public function markAllResolved(): void
    {
        if (! $this->profile) {
            return;
        }

        CredentialExposure::where('identity_profile_id', $this->profile->id)
            ->where('is_resolved', false)
            ->update(['is_resolved' => true, 'resolved_at' => now()]);

        app(RiskScoringService::class)->recalculateAndPersist($this->profile->id);
        $this->dispatch('notify', message: 'All credential actions marked complete.');
    }

    public function render()
    {
        $query = $this->profile
            ? CredentialExposure::where('identity_profile_id', $this->profile->id)
            : CredentialExposure::whereRaw('1 = 0');

        if ($this->filter === 'open') {
            $query->where('is_resolved', false);
        } elseif ($this->filter === 'resolved') {
            $query->where('is_resolved', true);
        }

        $exposures = $query->latest()->get();

        $stats = [
            'total' => $this->profile?->credentialExposures()->count() ?? 0,
            'open' => $this->profile?->credentialExposures()->where('is_resolved', false)->count() ?? 0,
            'resolved' => $this->profile?->credentialExposures()->where('is_resolved', true)->count() ?? 0,
        ];

        return view('livewire.credential-exposures', compact('exposures', 'stats'))
            ->layout('layouts.app', ['title' => 'Credential Exposure // TraceLess']);
    }
}
