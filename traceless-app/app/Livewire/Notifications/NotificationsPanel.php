<?php

namespace App\Livewire\Notifications;

use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationsPanel extends Component
{
    public bool $open = false;

    public function markRead(int $id): void
    {
        $alert = Alert::find($id);
        if ($alert && $alert->identityProfile?->user_id === Auth::id()) {
            $alert->update(['is_read' => true, 'read_at' => now()]);
        }
    }

    public function markAllRead(): void
    {
        Alert::whereHas('identityProfile', fn ($q) => $q->where('user_id', Auth::id()))
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    public function render()
    {
        $alerts = Alert::whereHas('identityProfile', fn ($q) => $q->where('user_id', Auth::id()))
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.notifications-panel', [
            'alerts' => $alerts,
            'unread' => $alerts->where('is_read', false)->count(),
        ]);
    }
}
