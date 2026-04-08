<?php

namespace app\exception;

class ValidationException extends \Exception
{
    /**
     * Field-level validation errors.
     * Keyed by field name, values are error message strings or arrays.
     *
     * @var array
     */
    protected array $errors;

    public function __construct(string $message = 'Validation failed', array $errors = [], ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, 40001, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
