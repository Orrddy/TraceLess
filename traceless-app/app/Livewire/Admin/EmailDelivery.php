<?php

namespace App\Livewire\Admin;

use App\Models\OutboundEmail;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Delivery health for the only channel that can sign a user in.
 */
class EmailDelivery extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $emails = OutboundEmail::query()
            ->with('user')
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(25);

        $lastSuccess = OutboundEmail::where('status', 'sent')->latest('sent_at')->first();

        return view('livewire.admin.email-delivery', [
            'emails' => $emails,
            'sent24h' => OutboundEmail::where('status', 'sent')->where('created_at', '>=', now()->subDay())->count(),
            'failed24h' => OutboundEmail::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'failedTotal' => OutboundEmail::where('status', 'failed')->count(),
            'lastSuccess' => $lastSuccess?->sent_at,
        ])->layout('layouts.app', ['title' => 'Email delivery — TraceLess']);
    }
}
