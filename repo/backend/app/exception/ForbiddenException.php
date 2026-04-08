<?php

namespace app\exception;

class ForbiddenException extends BusinessException
{
    public function __construct(string $message = 'Access denied', int $bizCode = 40301, int $httpStatus = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $bizCode, $httpStatus, $previous);
    }
}
