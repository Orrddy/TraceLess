<?php

namespace App\Livewire\Monitoring;

use App\Models\Alert;
use App\Models\IdentityProfile;
use App\Models\MonitoringSubscription;
use App\Models\Subscription;
use App\Services\ConsentService;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MonitoringPage extends Component
{
    public ?IdentityProfile $profile = null;

    public bool $isActive = false;

    public string $cadence = 'weekly';

    public array $options = [];

    public function mount(): void
    {
        $this->profile = IdentityProfile::where('user_id', Auth::id())->latest()->first();
        $subscription = $this->profile?->monitoringSubscription;

        $this->isActive = (bool) $subscription?->is_active;
        $this->cadence = $subscription?->cadence ?? 'weekly';
        $this->options = $this->normaliseOptions($subscription?->options);
    }

    public function save(): void
    {
        if (! $this->profile) {
            return;
        }

        $this->validate([
            'cadence' => 'required|in:daily,weekly,monthly',
            'options' => 'array',
        ]);

        // Plan gate: isDeepScan() delegates to App\Support\BetaAccess, which
        // grants recurring monitoring during the free beta.
        if ($this->isActive && ! Auth::user()->isDeepScan() && ! config('traceless.demo_mode')) {
            $this->addError('isActive', 'Recurring monitoring is a Deep Scan feature.');

            return;
        }

        MonitoringSubscription::updateOrCreate(
            ['identity_profile_id' => $this->profile->id],
            [
                'is_active' => $this->isActive,
                'cadence' => $this->cadence,
                'options' => $this->normaliseOptions($this->options),
                'next_scan_at' => $this->isActive ? now()->addDays($this->days()) : null,
                'last_scanned_at' => $this->profile->monitoringSubscription?->last_scanned_at,
            ]
        );

        $this->dispatch('notify', message: 'Monitoring preferences saved.');
    }

    public function runNow(ScanOrchestrator $orchestrator, ConsentService $consent)
    {
        if (! $this->profile) {
            return null;
        }

        // Plan gate: isDeepScan() delegates to App\Support\BetaAccess.
        if (! Auth::user()->isDeepScan()) {
            $this->addError('isActive', 'Deep Scan is required to run a monitoring scan.');

            return null;
        }

        if (! $consent->hasValidConsent($this->profile)) {
            $this->addError('isActive', 'Consent must be valid before monitoring can run.');

            return null;
        }

        $key = 'monitor-scan:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 2) || $this->profile->activeScan()) {
            $this->addError('isActive', 'A scan is already running or was started moments ago.');

            return null;
        }
        RateLimiter::hit($key, 120);

        $scan = $orchestrator->queueScan($this->profile, 'deep_scan');

        return redirect()->route('scans.show', $scan);
    }

    public function markAlertRead(int $id): void
    {
        $alert = Alert::findOrFail($id);
        Gate::authorize('update', $alert);

        $alert->update(['is_read' => true, 'read_at' => now()]);
    }

    public function markAllAlertsRead(): void
    {
        Alert::whereHas('identityProfile', fn ($q) => $q->where('user_id', Auth::id()))
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    public function enableDemoDeepScan(): void
    {
        abort_unless(config('traceless.demo_mode'), 403);
        Subscription::updateOrCreate(
            ['user_id' => Auth::id(), 'plan' => 'deep_scan'],
            ['status' => 'active', 'provider' => 'demo', 'current_period_end' => now()->addMonth()]
        );
        $this->dispatch('notify', message: 'Demo Deep Scan enabled.');
    }

    protected function normaliseOptions(?array $options): array
    {
        $keys = ['new_profiles', 'contact_info', 'data_brokers', 'reappearing', 'credentials', 'impersonation'];

        return collect($keys)->mapWithKeys(fn ($key) => [$key => (bool) ($options[$key] ?? true)])->all();
    }

    protected function days(): int
    {
        return match ($this->cadence) {
            'daily' => 1,
            'monthly' => 30,
            default => 7,
        };
    }

    public function render()
    {
        $subscription = $this->profile?->monitoringSubscription;
        $alerts = Alert::whereHas('identityProfile', fn ($q) => $q->where('user_id', Auth::id()))
            ->latest()
            ->take(20)
            ->get();

        return view('livewire.monitoring-page', [
            'subscription' => $subscription,
            'alerts' => $alerts,
            'isDeepScan' => Auth::user()->isDeepScan(),
            'demoMode' => (bool) config('traceless.demo_mode'),
        ])->layout('layouts.app', ['title' => 'Monitoring // TraceLess']);
    }
}
