<?php

namespace App\Printing;

enum PrinterState: string
{
    case Ready = 'ready';
    case Stopped = 'stopped';
    case Unknown = 'unknown';
}
