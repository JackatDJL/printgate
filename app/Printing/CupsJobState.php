<?php

namespace App\Printing;

enum CupsJobState: string
{
    case Active = 'active';
    case CompletedOrGone = 'completed_or_gone';
}
