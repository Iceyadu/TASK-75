<?php

return [
    'default' => 'local',
    'disks'   => [
        'local' => [
            'type'       => 'local',
            'root'       => env('MEDIA_STORAGE_PATH', app()->getRuntimePath() . 'storage'),
            'visibility' => 'public',
            'url'        => '/storage',
        ],
    ],
];
