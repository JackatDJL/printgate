<?php

namespace App\Printing;

enum PrintJobState: string
{
    case Pending = 'pending';
    case Submitting = 'submitting';
    case Submitted = 'submitted';
    case CompletedOrGone = 'completed_or_gone';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
