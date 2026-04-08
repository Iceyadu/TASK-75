<?php

namespace app\exception;

class RateLimitException extends BusinessException
{
    /**
     * Number of seconds the client should wait before retrying.
     *
     * @var int
     */
    protected int $retryAfter;

    public function __construct(string $message = 'Too many requests', int $retryAfter = 60, int $bizCode = 42901, int $httpStatus = 429, ?\Throwable $previous = null)
    {
        $this->retryAfter = $retryAfter;
        parent::__construct($message, $bizCode, $httpStatus, $previous);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
