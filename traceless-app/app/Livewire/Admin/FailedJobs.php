<?php

namespace App\Livewire\Admin;

use App\Services\AuditService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The queue dead-letter console. A failed job is a scan the user believes is
 * running; it must be visible and retryable from the panel.
 */
class FailedJobs extends Component
{
    use WithPagination;

    public bool $onlyRecent = false;

    public function mount(): void
    {
        // Failed job exceptions can contain user data; this component must be
        // unreachable even by a direct /livewire/update request.
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function updatedOnlyRecent(): void
    {
        $this->resetPage();
    }

    public function retryJob(string $uuid, AuditService $audit): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $audit->log(Auth::id(), 'admin.job_retried', "Requeued failed job {$uuid}.", request()->ip());
        $this->dispatch('notify', message: 'Job requeued.');
    }

    public function forgetJob(string $uuid, AuditService $audit): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        Artisan::call('queue:forget', ['id' => $uuid]);

        $audit->log(Auth::id(), 'admin.job_forgotten', "Deleted failed job {$uuid}.", request()->ip());
        $this->dispatch('notify', message: 'Failed job deleted.');
    }

    public function retryAll(AuditService $audit): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            $this->dispatch('notify', message: 'There are no failed jobs.');

            return;
        }

        Artisan::call('queue:retry', ['id' => ['all']]);

        $audit->log(Auth::id(), 'admin.jobs_retried_all', "Requeued {$count} failed job(s).", request()->ip());
        $this->dispatch('notify', message: "{$count} job(s) requeued.");
    }

    public function render()
    {
        $jobs = DB::table('failed_jobs')
            ->when($this->onlyRecent, fn ($query) => $query->where('failed_at', '>=', now()->subDay()))
            ->orderByDesc('failed_at')
            ->paginate(20);

        $jobs->getCollection()->transform(function ($row) {
            $row->payload_data = $this->safePayload($row->payload);
            $row->exception_excerpt = Str::limit(
                (string) Str::of($row->exception)->replace(["\r", "\n"], ' ')->squish(),
                400,
            );

            return $row;
        });

        return view('livewire.admin.failed-jobs', [
            'jobs' => $jobs,
            'total' => DB::table('failed_jobs')->count(),
            'last24h' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
            'pending' => DB::table('jobs')->count(),
        ])->layout('layouts.app', ['title' => 'Failed jobs — TraceLess']);
    }

    /**
     * Payloads can contain serialized objects; only expose the safe fields.
     *
     * @return array{display: string, queue: string}
     */
    private function safePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return ['display' => 'unreadable payload', 'queue' => 'unknown'];
        }

        return [
            'display' => class_basename((string) ($decoded['displayName'] ?? 'unknown job')),
            'queue' => (string) ($decoded['queue'] ?? 'default'),
        ];
    }
}
