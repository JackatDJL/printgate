<?php

namespace App\Printing;

final readonly class PrinterCapabilities
{
    /**
     * @param  list<PrinterMediaSize>  mediaSizes
     * @param  list<PrinterSides>  sides
     * @param  list<PrinterColorMode>  colorModes
     * @param  list<PrinterOrientation>  orientations
     * @param  array{media: list<string>, sides: list<string>, color: list<string>, orientation: list<string>}  unknownValues
     */
    public function __construct(
        public array $mediaSizes = [],
        public ?PrinterMediaSize $defaultMediaSize = null,
        public array $sides = [],
        public ?PrinterSides $defaultSides = null,
        public array $colorModes = [],
        public ?PrinterColorMode $defaultColorMode = null,
        public array $orientations = [],
        public ?PrinterOrientation $defaultOrientation = null,
        public array $unknownValues = [
            'media' => [],
            'sides' => [],
            'color' => [],
            'orientation' => [],
        ],
    ) {}
}
