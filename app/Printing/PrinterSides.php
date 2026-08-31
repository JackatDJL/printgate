<?php

namespace App\Printing;

enum PrinterSides: string
{
    case OneSided = 'one-sided';
    case TwoSidedLongEdge = 'two-sided-long-edge';
    case TwoSidedShortEdge = 'two-sided-short-edge';
}
