<?php

namespace App\Livewire\Dashboard;

use App\Models\Alert;
use App\Models\CredentialExposure;
use App\Models\Finding;
use App\Models\IdentityProfile;
use App\Models\RemovalTask;
use App\Models\Scan;
use App\Services\ConsentService;
use App\Services\RiskScoringService;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Dashboard extends Component
{
    public ?IdentityProfile $profile = null;

    public array $scoreData = [];

    public $recentFindings = [];

    public $activeTasks = [];

    public $credentialExposures = [];

    public $alerts = [];

    public $recentScans = [];

    public array $removalProgress = [];

    public array $categoryStats = [];

    public array $riskDistribution = [];

    public array $scanTrend = [];

    public bool $isScanning = false;

    public function mount(RiskScoringService $riskScorer): void
    {
        $this->loadData($riskScorer);
    }

    protected function loadData(?RiskScoringService $riskScorer = null): void
    {
        $riskScorer ??= app(RiskScoringService::class);
        $this->profile = IdentityProfile::where('user_id', Auth::id())->latest()->first();

        if (! $this->profile) {
            $this->scoreData = [];

            return;
        }

        $this->scoreData = $riskScorer->calculate($this->profile->id);
        $this->recentFindings = Finding::where('identity_profile_id', $this->profile->id)->latest()->take(6)->get();
        $this->activeTasks = RemovalTask::where('identity_profile_id', $this->profile->id)->latest()->take(6)->get();
        $this->credentialExposures = CredentialExposure::where('identity_profile_id', $this->profile->id)->latest()->get();
        $this->alerts = Alert::where('identity_profile_id', $this->profile->id)->where('is_read', false)->latest()->take(4)->get();
        $this->recentScans = Scan::where('identity_profile_id', $this->profile->id)->latest()->take(6)->get();
        $this->isScanning = (bool) $this->profile->activeScan();

        $tasks = RemovalTask::where('identity_profile_id', $this->profile->id)->get();
        $this->removalProgress = [
            'total' => $tasks->count(),
            'removed' => $tasks->where('status', 'removed')->count(),
            'percent' => $tasks->count() ? (int) round(($tasks->where('status', 'removed')->count() / $tasks->count()) * 100) : 0,
        ];

        $allFindings = Finding::where('identity_profile_id', $this->profile->id)->get();
        $this->riskDistribution = [
            'critical' => $allFindings->where('risk', 'critical')->count(),
            'high' => $allFindings->where('risk', 'high')->count(),
            'moderate' => $allFindings->where('risk', 'moderate')->count(),
            'low' => $allFindings->where('risk', 'low')->count(),
        ];

        $this->categoryStats = $allFindings
            ->groupBy('category')
            ->map(fn ($group, $category) => [
                'category' => $category,
                'label' => str($category)->replace('_', ' ')->title()->toString(),
                'count' => $group->count(),
                'max_risk' => $group->sortByDesc(fn ($f) => array_search($f->risk, ['low', 'moderate', 'high', 'critical'], true))->first()?->risk ?? 'low',
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values()
            ->all();

        $this->scanTrend = Scan::where('identity_profile_id', $this->profile->id)
            ->where('status', 'completed')
            ->oldest()
            ->take(8)
            ->get()
            ->map(fn ($scan) => ['label' => $scan->completed_at?->format('M d') ?? '—', 'value' => $scan->findings_count])
            ->values()
            ->all();
    }

    public function triggerQuickScan(ScanOrchestrator $orchestrator, ConsentService $consent)
    {
        if (! $this->profile) {
            return redirect()->route('identity.create');
        }

        if (! $consent->hasValidConsent($this->profile)) {
            $this->addError('scan', 'Consent is missing for this identity. Re-confirm consent before scanning.');

            return null;
        }

        $key = 'quick-scan:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('scan', 'Please wait a moment before starting another scan.');

            return null;
        }
        RateLimiter::hit($key, 60);

        if ($this->profile->activeScan()) {
            return redirect()->route('scans.show', $this->profile->activeScan());
        }

        $scan = $orchestrator->queueScan($this->profile, 'snapshot');

        return redirect()->route('scans.show', $scan);
    }

    public function markResolved(int $credId): void
    {
        $cred = CredentialExposure::find($credId);
        if ($cred && $cred->identity_profile_id === $this->profile?->id) {
            $cred->update(['is_resolved' => true, 'resolved_at' => now()]);
            app(RiskScoringService::class)->recalculateAndPersist($this->profile->id);
            $this->loadData();
        }
    }

    public function dismissAlert(int $alertId): void
    {
        $alert = Alert::findOrFail($alertId);
        Gate::authorize('update', $alert);

        $alert->update(['is_read' => true, 'read_at' => now()]);
        $this->loadData();
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard')
            ->layout('layouts.app', ['title' => 'Command Center // TraceLess']);
    }
}
