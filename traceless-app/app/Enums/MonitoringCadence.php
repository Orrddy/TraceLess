<?php

namespace App\Enums;

enum MonitoringCadence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function days(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
            self::Monthly => 30,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
