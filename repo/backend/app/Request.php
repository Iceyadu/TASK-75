<?php

namespace app;

class Request extends \think\Request
{
    /**
     * Authenticated user model instance.
     * Set by AuthMiddleware after successful authentication.
     *
     * @var \app\model\User|null
     */
    public $user = null;

    /**
     * Current organization ID.
     * Set by AuthMiddleware from the authenticated user's organization.
     *
     * @var int|null
     */
    public $orgId = null;

    /**
     * Whether this request was authenticated via API token (vs session).
     *
     * @var bool
     */
    public $tokenAuth = false;
}
