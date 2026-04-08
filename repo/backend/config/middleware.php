<?php

return [
    'alias'  => [
        'auth'          => \app\middleware\AuthMiddleware::class,
        'rbac'          => \app\middleware\RbacMiddleware::class,
        'rate_limit'    => \app\middleware\RateLimitMiddleware::class,
        'hotlink'       => \app\middleware\HotlinkMiddleware::class,
        'cors'          => \app\middleware\CorsMiddleware::class,
        'org_isolation' => \app\middleware\OrgIsolationMiddleware::class,
    ],
    'global' => [
        \app\middleware\CorsMiddleware::class,
    ],
];
