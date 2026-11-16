<?php

namespace App\Livewire\Tools;

use App\Services\AbuseIpdbService;
use App\Services\AuditService;
use App\Services\IpReputationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

class IpCheck extends Component
{
    public string $ip = '';

    public ?array $result = null;

    /** AbuseIPDB signal, when a key is configured. */
    public ?array $abuse = null;

    public ?string $error = null;

    public ?string $abuseError = null;

    public function mount(): void
    {
        // The address this request actually came from, which is the one the user
        // is most likely asking about.
        $this->ip = (string) request()->ip();
    }

    public function check(IpReputationService $lookup, AbuseIpdbService $abuse): void
    {
        $this->validate([
            'ip' => ['nullable', 'ip'],
        ]);

        $this->error = null;
        $this->result = null;
        $this->abuse = null;
        $this->abuseError = null;

        try {
            $this->result = $lookup->lookup($this->ip !== '' ? $this->ip : null);
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        if (config('traceless.ip_lookup.abuseipdb_key')) {
            try {
                $this->abuse = $abuse->check($this->result['ip']);
            } catch (Throwable $exception) {
                // The geo half succeeded, so keep it and report the missing half.
                $this->abuseError = $exception->getMessage();
            }
        }

        app(AuditService::class)->log(
            Auth::id(),
            'ip_lookup',
            'IP exposure lookup run for '.$this->result['ip'].'.',
            request()->ip(),
        );
    }

    public function render()
    {
        return view('livewire.tools.ip-check')
            ->layout('layouts.app', ['title' => 'IP check // TraceLess']);
    }
}
