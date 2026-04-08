<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * Validation rules for order endpoints.
 */
class OrderValidate extends Validate
{
    /**
     * Validation rules.
     *
     * @var array<string, string>
     */
    protected $rule = [
        'listing_id'  => 'require|integer',
        'driver_notes' => 'max:500',
        'reason_code' => 'in:DRIVER_UNAVAILABLE,PASSENGER_CHANGED_PLANS,VEHICLE_ISSUE,SCHEDULE_CONFLICT,OTHER',
        'reason_text' => 'max:500',
        'reason'      => 'require|max:1000',
        'resolution'  => 'require|max:2000',
        'outcome'     => 'require|in:passenger_favor,driver_favor,mutual,dismissed',
    ];

    /**
     * Custom error messages.
     *
     * @var array<string, string>
     */
    protected $message = [
        'listing_id.require'  => 'Listing ID is required.',
        'listing_id.integer'  => 'Listing ID must be an integer.',
        'driver_notes.max'    => 'Driver notes must not exceed 500 characters.',
        'reason_code.in'      => 'Reason code must be one of: DRIVER_UNAVAILABLE, PASSENGER_CHANGED_PLANS, VEHICLE_ISSUE, SCHEDULE_CONFLICT, OTHER.',
        'reason_text.max'     => 'Reason text must not exceed 500 characters.',
        'reason.require'      => 'A reason is required.',
        'reason.max'          => 'Reason must not exceed 1000 characters.',
        'resolution.require'  => 'A resolution is required.',
        'resolution.max'      => 'Resolution must not exceed 2000 characters.',
        'outcome.require'     => 'An outcome is required.',
        'outcome.in'          => 'Outcome must be one of: passenger_favor, driver_favor, mutual, dismissed.',
    ];

    /**
     * Validation scenes.
     *
     * @var array<string, string[]>
     */
    protected $scene = [
        'create'  => ['listing_id', 'driver_notes'],
        'cancel'  => ['reason_code', 'reason_text'],
        'dispute' => ['reason'],
        'resolve' => ['resolution', 'outcome'],
    ];
}
