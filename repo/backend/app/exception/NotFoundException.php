<?php

namespace app\exception;

class NotFoundException extends BusinessException
{
    public function __construct(string $message = 'Resource not found', int $bizCode = 40401, int $httpStatus = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $bizCode, $httpStatus, $previous);
    }
}
