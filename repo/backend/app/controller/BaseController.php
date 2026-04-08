<?php
declare(strict_types=1);

namespace app\controller;

use app\Request;

/**
 * Base controller providing access to the typed Request instance.
 */
abstract class BaseController
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * Constructor - inject the application request.
     */
    public function __initialize(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * ThinkPHP 6 automatically calls __construct or uses the container.
     * We rely on property injection via the request() helper when needed.
     */
    protected function getRequest(): Request
    {
        if ($this->request === null) {
            $this->request = app('request');
        }
        return $this->request;
    }
}
