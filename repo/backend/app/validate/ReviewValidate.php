<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * Validation rules for review endpoints.
 */
class ReviewValidate extends Validate
{
    /**
     * Validation rules.
     *
     * @var array<string, string>
     */
    protected $rule = [
        'order_id' => 'require|integer',
        'rating'   => 'require|integer|between:1,5',
        'text'     => 'require|min:1|max:1000',
    ];

    /**
     * Custom error messages.
     *
     * @var array<string, string>
     */
    protected $message = [
        'order_id.require' => 'Order ID is required.',
        'order_id.integer' => 'Order ID must be an integer.',
        'rating.require'   => 'A rating is required.',
        'rating.integer'   => 'Rating must be an integer.',
        'rating.between'   => 'Rating must be between 1 and 5.',
        'text.require'     => 'Review text is required.',
        'text.min'         => 'Review text must be at least 1 character.',
        'text.max'         => 'Review text must not exceed 1000 characters.',
    ];

    /**
     * Validation scenes.
     *
     * @var array<string, string[]>
     */
    protected $scene = [
        'create' => ['order_id', 'rating', 'text'],
        'update' => ['rating', 'text'],
    ];

    /**
     * Override scene rules for update – all fields become optional.
     *
     * @return ReviewValidate
     */
    public function sceneUpdate(): ReviewValidate
    {
        return $this->only(['rating', 'text'])
            ->remove('rating', 'require')
            ->remove('text', 'require');
    }
}
