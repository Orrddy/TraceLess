<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EmailOtp;
use App\Models\Finding;
use App\Models\OutboundEmail;
use App\Models\Report;
use App\Models\Scan;
use Illuminate\Console\Command;

class PruneTraceLessData extends Command
{
    protected $signature = 'traceless:prune {--dry-run}';

    protected $description = 'Apply TraceLess data-retention windows to scan telemetry, reports, audit logs and outbound email.';

    public function handle(): int
    {
        $scanCutoff = now()->subDays((int) config('traceless.retention.scan_telemetry_days'));
        $reportCutoff = now()->subDays((int) config('traceless.retention.report_days'));
        $auditCutoff = now()->subDays((int) config('traceless.retention.audit_days'));
        $outboundCutoff = now()->subDays((int) config('traceless.retention.outbound_email_days', 90));

        $scans = Scan::whereIn('status', ['completed', 'failed', 'cancelled'])
            ->where('created_at', '<', $scanCutoff);

        $reports = Report::where('created_at', '<', $reportCutoff);
        $audits = AuditLog::where('created_at', '<', $auditCutoff);
        $otps = EmailOtp::where('expires_at', '<', now()->subDay());
        // Delivery rows contain recipient addresses, so they get a shorter
        // window than scan telemetry and never a window shorter than configured.
        $outbound = OutboundEmail::where('created_at', '<', $outboundCutoff);

        if ($this->option('dry-run')) {
            $this->table(['Record', 'Count'], [
                ['scan telemetry', (clone $scans)->count()],
                ['reports', (clone $reports)->count()],
                ['audit logs', (clone $audits)->count()],
                ['expired one-time codes', (clone $otps)->count()],
                ['outbound emails', (clone $outbound)->count()],
            ]);

            return self::SUCCESS;
        }

        $scanCount = (clone $scans)->count();

        // Findings belong to their scan. Deleting the scan while keeping newer
        // findings orphaned them (scan_id is nullOnDelete), so they could never
        // be pruned afterwards. Remove them together, oldest scan first.
        $scansToDelete = (clone $scans)->pluck('id');
        Finding::whereIn('scan_id', $scansToDelete)->delete();
        Scan::whereIn('id', $scansToDelete)->delete();

        $reportCount = (clone $reports)->delete();
        $auditCount = (clone $audits)->delete();
        $otpCount = (clone $otps)->delete();
        $outboundCount = (clone $outbound)->delete();

        AuditLog::create([
            'user_id' => null,
            'action' => 'retention_purge',
            'details' => "Pruned {$scanCount} scans, {$reportCount} reports, {$otpCount} expired codes, {$outboundCount} outbound emails and {$auditCount} audit records.",
            'ip_address' => null,
        ]);

        $this->info("Pruned {$scanCount} scans, {$reportCount} reports, {$otpCount} expired codes, {$outboundCount} outbound emails and {$auditCount} audit records.");

        return self::SUCCESS;
    }
}
