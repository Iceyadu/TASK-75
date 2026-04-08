<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * Validation rules for API token endpoints.
 */
class TokenValidate extends Validate
{
    /**
     * Validation rules.
     *
     * @var array<string, string>
     */
    protected $rule = [
        'name'            => 'require|min:1|max:100',
        'expires_in_days' => 'integer|between:1,365',
    ];

    /**
     * Custom error messages.
     *
     * @var array<string, string>
     */
    protected $message = [
        'name.require'            => 'Token name is required.',
        'name.min'                => 'Token name must be at least 1 character.',
        'name.max'                => 'Token name must not exceed 100 characters.',
        'expires_in_days.integer' => 'Expiration days must be an integer.',
        'expires_in_days.between' => 'Expiration must be between 1 and 365 days.',
    ];

    /**
     * Validation scenes.
     *
     * @var array<string, string[]>
     */
    protected $scene = [
        'create' => ['name', 'expires_in_days'],
    ];
}
