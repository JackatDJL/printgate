<?php

return [

    'bind' => [
        'host' => env('PRINTGATE_BIND_HOST', '127.0.0.1'),
        'port' => env('PRINTGATE_PORT', 5901),
    ],

    'documents' => [
        'disk' => 'printgate-documents',
        'max_upload_megabytes' => env('PRINTGATE_MAX_UPLOAD_MB', 50),
        'retention_minutes' => env('PRINTGATE_RETENTION_MINUTES', 60),
    ],

    'authentication' => [
        'mode' => env('PRINTGATE_AUTH_MODE', 'local'),
    ],

];
