<?php

namespace App\Livewire\Tools;

use App\Services\AuditService;
use App\Services\IpIntelligence\IpAddressNormalizer;
use App\Services\IpIntelligence\IpIntelligenceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Network intelligence tool.
 *
 * Backed by the same multi-provider engine the landing page uses: cache-first,
 * 48h persistent cache, single-flight analysis, and honest "unavailable" states
 * when a provider is missing or silent. The page never invents a field.
 */
class IpCheck extends Component
{
    public string $ip = '';

    /** idle | warming | ready | unavailable */
    public string $state = 'idle';

    public ?array $result = null;

    public ?string $error = null;

    public function mount(): void
    {
        $candidate = IpAddressNormalizer::normalize((string) request()->ip());

        $this->ip = $candidate ?? '';
    }

    public function useMyIp(): void
    {
        $this->ip = IpAddressNormalizer::normalize((string) request()->ip()) ?? '';
        $this->check();
    }

    public function check(IpIntelligenceService $service): void
    {
        $this->validate([
            'ip' => ['required', 'string', 'max:45'],
        ], [
            'ip.required' => 'Enter an IP address to check.',
        ]);

        $this->error = null;
        $this->result = null;
        $this->state = 'idle';

        $normalized = IpAddressNormalizer::normalize(trim($this->ip));

        if ($normalized === null) {
            $this->error = 'That is not a public IPv4 or IPv6 address. Private, reserved and loopback ranges have no public network record.';

            return;
        }

        $this->ip = $normalized;
        $this->resolve($service);
    }

    /** Called by wire:poll while an analysis is warming. */
    public function refresh(IpIntelligenceService $service): void
    {
        if ($this->state !== 'warming' || $this->ip === '') {
            return;
        }

        $this->resolve($service);
    }

    private function resolve(IpIntelligenceService $service): void
    {
        $result = $service->lookup($this->ip);

        if ($result['status'] === 'unavailable') {
            $this->state = 'unavailable';
            $this->error = 'Network intelligence providers are not configured yet, so there is nothing honest to show.';

            return;
        }

        if ($result['status'] === 'warming') {
            $this->state = 'warming';

            return;
        }

        $this->result = $result['data'];
        $this->state = 'ready';

        app(AuditService::class)->log(
            Auth::id(),
            'ip_lookup',
            'Network intelligence lookup run for '.$this->ip.'.',
            request()->ip(),
        );
    }

    public function render()
    {
        return view('livewire.tools.ip-check')
            ->layout('layouts.app', ['title' => 'Network intelligence // TraceLess']);
    }
}
