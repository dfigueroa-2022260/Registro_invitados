<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],
        // Solo para migrar las firmas antiguas (v1-v3) a la base de datos; las nuevas ya no usan carpeta.
        'firmas' => [
            'driver' => 'local',
            'root' => env('FIRMAS_DIR') ?: storage_path('app/firmas'),
            'throw' => true,
            'report' => false,
        ],
    ],

    'links' => [],
];
