<?php

namespace App\Livewire\Scans;

use App\Models\IdentityProfile;
use App\Models\MonitoringSubscription;
use App\Models\Subscription;
use App\Services\AuditService;
use App\Services\ConsentService;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ScanCreate extends Component
{
    public ?int $profile_id = null;

    public string $type = 'snapshot';

    public string $cadence = 'weekly';

    public bool $recurring = false;

    public bool $consent_confirmed = false;

    public $profiles = [];

    public function mount(): void
    {
        $this->profiles = IdentityProfile::where('user_id', Auth::id())->latest()->get();
        $this->profile_id = $this->profiles->first()?->id;
    }

    public function start(ScanOrchestrator $orchestrator, ConsentService $consent, AuditService $audit)
    {
        $this->validate([
            'profile_id' => 'required|exists:identity_profiles,id',
            'type' => 'required|in:snapshot,deep_scan',
            'cadence' => 'required_if:recurring,true|in:daily,weekly,monthly',
            'consent_confirmed' => 'accepted',
        ]);

        $profile = IdentityProfile::findOrFail($this->profile_id);
        Gate::authorize('view', $profile);

        if (! $consent->hasValidConsent($profile)) {
            $this->addError('consent_confirmed', 'A valid consent record is required for this identity.');

            return null;
        }

        if ($this->type === 'deep_scan' && ! Auth::user()->isDeepScan()) {
            $this->addError('type', 'Deep Scan requires an active Deep Scan plan. You can enable the demo plan from this page in demo mode.');

            return null;
        }

        $key = 'scan-create:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('type', 'Please wait a minute before starting another scan.');

            return null;
        }
        RateLimiter::hit($key, 60);

        if ($profile->activeScan()) {
            return redirect()->route('scans.show', $profile->activeScan());
        }

        $scan = $orchestrator->queueScan($profile, $this->type);

        if ($this->recurring && $this->type === 'deep_scan') {
            MonitoringSubscription::updateOrCreate(
                ['identity_profile_id' => $profile->id],
                [
                    'is_active' => true,
                    'cadence' => $this->cadence,
                    'last_scanned_at' => now(),
                    'next_scan_at' => now()->addDays(match ($this->cadence) {
                        'daily' => 1,
                        'monthly' => 30,
                        default => 7,
                    }),
                ]
            );
        }

        $audit->log(Auth::id(), 'scan_started', "Scan #{$scan->id} ({$this->type}) queued.", request()->ip());

        return redirect()->route('scans.show', $scan);
    }

    /**
     * Demo-only shortcut. Never active in production because it is guarded by
     * config('traceless.demo_mode').
     */
    public function enableDemoDeepScan(): void
    {
        abort_unless(config('traceless.demo_mode'), 403);

        Subscription::updateOrCreate(
            ['user_id' => Auth::id(), 'plan' => 'deep_scan'],
            [
                'status' => 'active',
                'provider' => 'demo',
                'current_period_end' => now()->addMonth(),
            ]
        );

        $this->dispatch('notify', message: 'Demo Deep Scan enabled. No payment was taken.');
    }

    public function render()
    {
        return view('livewire.scan-create', [
            'isDeepScan' => Auth::user()->isDeepScan(),
            'demoMode' => (bool) config('traceless.demo_mode'),
        ])->layout('layouts.app', ['title' => 'New Scan // TraceLess']);
    }
}
