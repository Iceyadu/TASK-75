<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * Validation rules for authentication endpoints.
 */
class AuthValidate extends Validate
{
    /**
     * Validation rules.
     *
     * @var array<string, string>
     */
    protected $rule = [
        'name'                  => 'require|min:2|max:100',
        'email'                 => 'require|email',
        'password'              => 'require|min:8|max:72|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        'password_confirmation' => 'require|confirm:password',
        'organization_code'     => 'require',
    ];

    /**
     * Custom error messages.
     *
     * @var array<string, string>
     */
    protected $message = [
        'name.require'                  => 'Name is required.',
        'name.min'                      => 'Name must be at least 2 characters.',
        'name.max'                      => 'Name must not exceed 100 characters.',
        'email.require'                 => 'Email address is required.',
        'email.email'                   => 'Please provide a valid email address.',
        'password.require'              => 'Password is required.',
        'password.min'                  => 'Password must be at least 8 characters.',
        'password.max'                  => 'Password must not exceed 72 characters.',
        'password.regex'                => 'Password must contain at least one lowercase letter, one uppercase letter, and one digit.',
        'password_confirmation.require' => 'Password confirmation is required.',
        'password_confirmation.confirm' => 'Password confirmation does not match.',
        'organization_code.require'     => 'Organization code is required.',
    ];

    /**
     * Validation scenes.
     *
     * @var array<string, string[]>
     */
    protected $scene = [
        'register' => ['name', 'email', 'password', 'password_confirmation', 'organization_code'],
        'login'    => ['email', 'password', 'organization_code'],
    ];
}
