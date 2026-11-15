<?php

namespace App\Livewire\Scans;

use App\Models\Finding;
use App\Models\Scan;
use App\Services\ScanOrchestrator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ScanProgress extends Component
{
    public Scan $scan;

    public bool $confirmCancel = false;

    public function mount(Scan $scan): void
    {
        abort_unless($scan->identityProfile->user_id === Auth::id(), 403);
        $this->scan = $scan;
    }

    public function cancel(ScanOrchestrator $orchestrator): void
    {
        abort_unless($this->scan->identityProfile->user_id === Auth::id(), 403);

        if ($this->scan->isActive()) {
            $orchestrator->cancel($this->scan);
            $this->scan->refresh();
            $this->confirmCancel = false;
            $this->dispatch('notify', message: 'Scan cancelled. No further sources were checked.');
        }
    }

    public function render()
    {
        $this->scan->refresh();
        $findings = Finding::where('scan_id', $this->scan->id)->latest()->take(8)->get();
        $sources = $this->scan->scanSources()->orderBy('id')->get();

        return view('livewire.scan-progress', compact('findings', 'sources'))
            ->layout('layouts.app', ['title' => 'Scan #'.$this->scan->id.' // TraceLess']);
    }
}
