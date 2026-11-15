<?php

namespace App\Enums;

enum RemovalStatus: string
{
    case NotStarted = 'not_started';
    case ReadyToRemove = 'ready_to_remove';
    case InProgress = 'in_progress';
    case WaitingForSource = 'waiting_for_source';
    case UserActionRequired = 'user_action_required';
    case Removed = 'removed';
    case CouldNotVerify = 'could_not_verify';
    case Reappeared = 'reappeared';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::ReadyToRemove => 'Ready to remove',
            self::InProgress => 'In progress',
            self::WaitingForSource => 'Waiting for source',
            self::UserActionRequired => 'User action required',
            self::Removed => 'Removed',
            self::CouldNotVerify => 'Could not verify',
            self::Reappeared => 'Reappeared',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
