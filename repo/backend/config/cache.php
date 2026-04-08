<?php

return [
    'default' => 'file',
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => app()->getRuntimePath() . 'cache',
            'prefix'     => '',
            'serialize'  => true,
            'expire'     => 0,
        ],
    ],
];
