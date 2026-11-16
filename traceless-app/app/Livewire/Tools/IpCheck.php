<?php

namespace App\Livewire\Tools;

use App\Services\AuditService;
use App\Services\IpReputationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

class IpCheck extends Component
{
    public string $ip = '';

    public ?array $result = null;

    public ?string $error = null;

    public function mount(): void
    {
        // The address this request actually came from, which is the one the user
        // is most likely asking about.
        $this->ip = (string) request()->ip();
    }

    public function check(IpReputationService $lookup): void
    {
        $this->validate([
            'ip' => ['nullable', 'ip'],
        ]);

        $this->error = null;
        $this->result = null;

        try {
            $this->result = $lookup->lookup($this->ip !== '' ? $this->ip : null);
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        app(AuditService::class)->log(
            Auth::id(),
            'ip_lookup',
            'IP exposure lookup run for '.$this->ip.'.',
            request()->ip(),
        );
    }

    public function render()
    {
        return view('livewire.tools.ip-check')
            ->layout('layouts.app', ['title' => 'IP check // TraceLess']);
    }
}
