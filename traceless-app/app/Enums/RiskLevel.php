<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function penalty(): int
    {
        return match ($this) {
            self::Critical => 15,
            self::High => 8,
            self::Moderate => 3,
            self::Low => 1,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
