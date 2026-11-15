<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Queued = 'queued';
    case Initializing = 'initializing';
    case CheckingSources = 'checking_sources';
    case NormalizingResults = 'normalizing_results';
    case CalculatingRisk = 'calculating_risk';
    case GeneratingReport = 'generating_report';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Initializing => 'Initializing',
            self::CheckingSources => 'Checking sources',
            self::NormalizingResults => 'Normalizing results',
            self::CalculatingRisk => 'Calculating risk',
            self::GeneratingReport => 'Generating report',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
