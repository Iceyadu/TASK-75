<?php

namespace app\exception;

class BusinessException extends \Exception
{
    /**
     * Business-specific error code.
     *
     * @var int
     */
    protected int $bizCode;

    /**
     * HTTP status code to return.
     *
     * @var int
     */
    protected int $httpStatus;

    public function __construct(string $message = 'Business error', int $bizCode = 40001, int $httpStatus = 400, ?\Throwable $previous = null)
    {
        $this->bizCode    = $bizCode;
        $this->httpStatus = $httpStatus;
        parent::__construct($message, $bizCode, $previous);
    }

    public function getBizCode(): int
    {
        return $this->bizCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
