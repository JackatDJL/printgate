<?php

namespace App\Printing;

enum PrinterMediaSize: string
{
    case A4 = 'a4';
    case A5 = 'a5';
    case Letter = 'letter';
    case Legal = 'legal';
}
