<?php

return [
    'default'     => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'type'            => 'mysql',
            'hostname'        => env('DB_HOST', '127.0.0.1'),
            'hostport'        => env('DB_PORT', '3306'),
            'database'        => env('DB_DATABASE', 'ridecircle'),
            'username'        => env('DB_USERNAME', 'root'),
            'password'        => env('DB_PASSWORD', ''),
            'params'          => [],
            'charset'         => 'utf8mb4',
            'collation'       => 'utf8mb4_unicode_ci',
            'prefix'          => '',
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => env('APP_DEBUG', false),
            'fields_cache'    => !env('APP_DEBUG', false),
        ],
    ],
];
