<?php

namespace App\Enums;

enum FindingStatus: string
{
    case NeedsUserAction = 'needs_user_action';
    case ReadyToRemove = 'ready_to_remove';
    case InProgress = 'in_progress';
    case WaitingForSource = 'waiting_for_source';
    case UserActionRequired = 'user_action_required';
    case Removed = 'removed';
    case CouldNotVerify = 'could_not_verify';
    case Reappeared = 'reappeared';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
