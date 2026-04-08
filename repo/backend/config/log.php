<?php

return [
    'default'  => 'file',
    'channels' => [
        'file' => [
            'type'           => 'File',
            'path'           => app()->getRuntimePath() . 'log',
            'level'          => env('LOG_LEVEL', 'info') ? [env('LOG_LEVEL', 'info')] : [],
            'max_files'      => 90,
            'file_size'      => 20971520,
            'apart_level'    => ['error', 'warning'],
            'time_format'    => 'Y-m-d\TH:i:s\Z',
            'single'         => false,
        ],
    ],
];
