<?php

namespace App\Livewire\Reports;

use App\Events\ReportGenerated;
use App\Models\IdentityProfile;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ReportsPage extends Component
{
    public ?IdentityProfile $profile = null;

    public string $type = 'snapshot';

    public function mount(): void
    {
        $this->profile = IdentityProfile::where('user_id', Auth::id())->latest()->first();
    }

    public function generate(ReportService $reports)
    {
        if (! $this->profile) {
            return null;
        }

        $this->validate(['type' => 'required|in:snapshot,deep_scan']);

        if ($this->type === 'deep_scan' && ! Auth::user()->isDeepScan()) {
            $this->addError('type', 'Deep reports require an active Deep Scan plan.');

            return null;
        }

        $key = 'report-generate:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('type', 'Please wait before generating another report.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $report = $reports->generate($this->profile, $this->type);
        event(new ReportGenerated($report));

        $this->dispatch('notify', message: 'Report generated. Download it below.');
    }

    public function render()
    {
        $reports = $this->profile
            ? Report::where('identity_profile_id', $this->profile->id)->latest()->get()
            : collect();

        return view('livewire.reports-page', [
            'reports' => $reports,
            'isDeepScan' => Auth::user()->isDeepScan(),
        ])->layout('layouts.app', ['title' => 'Reports // TraceLess']);
    }
}
