<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\AuditService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportDownloadController extends Controller
{
    public function __invoke(Request $request, Report $report, ReportService $reports, AuditService $audit)
    {
        abort_unless($report->identityProfile->user_id === $request->user()->id, 403);
        abort_unless(hash_equals((string) $report->download_token, (string) $request->query('token')), 403);

        $report->increment('download_count');
        $report->refresh();

        $audit->log($request->user()->id, 'report_downloaded', "Report #{$report->id} downloaded.", $request->ip());

        $findings = $report->identityProfile->findings()->with('removalTask')->orderByRaw(
            "CASE risk WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END"
        )->get();

        return response()
            ->view('reports.download', [
                'report' => $report,
                'profile' => $report->identityProfile,
                'summary' => $reports->summaryArray($report),
                'findings' => $findings,
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="traceless-report-'.$report->id.'.html"');
    }
}
