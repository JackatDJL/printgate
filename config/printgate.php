<?php

return [

    'role' => env('PRINTGATE_ROLE', 'local'),

    'bind' => [
        'host' => env('PRINTGATE_BIND_HOST', '127.0.0.1'),
        'port' => env('PRINTGATE_PORT', 5901),
    ],

    'documents' => [
        'disk' => 'printgate-documents',
        'max_upload_megabytes' => env('PRINTGATE_MAX_UPLOAD_MB', 50),
        'retention_minutes' => env('PRINTGATE_RETENTION_MINUTES', 60),
    ],

    'connect' => [
        'max_active_jobs_per_owner' => env('PRINTGATE_CONNECT_MAX_ACTIVE_JOBS', 10),
    ],

    'authentication' => [
        'mode' => env('PRINTGATE_AUTH_MODE', 'local'),
    ],

];
