<?php

namespace app\exception;

class AuthException extends BusinessException
{
    public function __construct(string $message = 'Authentication required', int $bizCode = 40101, int $httpStatus = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $bizCode, $httpStatus, $previous);
    }
}
