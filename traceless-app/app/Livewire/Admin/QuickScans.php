<?php

namespace App\Livewire\Admin;

use App\Models\QuickScanRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class QuickScans extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function render()
    {
        return view('livewire.admin.quick-scans', [
            'records' => QuickScanRecord::latest()->paginate(25),
            'total' => QuickScanRecord::count(),
            'today' => QuickScanRecord::where('created_at', '>=', now()->startOfDay())->count(),
            'withGreen' => QuickScanRecord::where('public_matches', '>', 0)->count(),
        ])->layout('layouts.app', ['title' => 'Admin quick scans — TraceLess']);
    }
}
