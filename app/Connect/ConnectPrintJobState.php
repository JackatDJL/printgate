<?php

namespace App\Connect;

enum ConnectPrintJobState: string
{
    case Queued = 'queued';
    case Submitting = 'submitting';
    case Submitted = 'submitted';
    case Failed = 'failed';
    case Uncertain = 'uncertain';
    case CompletedOrGone = 'completed_or_gone';

    public function isPollable(): bool
    {
        return in_array($this, [self::Queued, self::Submitting], true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Queued => in_array($next, [self::Submitting, self::Failed, self::Uncertain, self::CompletedOrGone], true),
            self::Submitting => in_array($next, [self::Submitted, self::Failed, self::Uncertain, self::CompletedOrGone], true),
            self::Submitted => in_array($next, [self::CompletedOrGone, self::Uncertain], true),
            self::Failed, self::Uncertain, self::CompletedOrGone => false,
        };
    }
}
