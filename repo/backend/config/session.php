<?php

return [
    'id'             => '',
    'type'           => env('SESSION_TYPE', 'file'),
    'store'          => null,
    'expire'         => (int) env('SESSION_LIFETIME', 120) * 60,
    'var_session_id' => '',
    'name'           => 'PHPSESSID',
    'prefix'         => 'ridecircle_',
    'serialize'      => null,
];
