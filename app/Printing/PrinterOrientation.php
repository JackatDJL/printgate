<?php

namespace App\Printing;

enum PrinterOrientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';
    case ReversePortrait = 'reverse-portrait';
    case ReverseLandscape = 'reverse-landscape';
}
