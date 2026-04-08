<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * Validation rules for listing endpoints.
 */
class ListingValidate extends Validate
{
    /**
     * Validation rules.
     *
     * @var array<string, string>
     */
    protected $rule = [
        'title'             => 'require|min:5|max:200',
        'pickup_address'    => 'require|min:5|max:500',
        'dropoff_address'   => 'require|min:5|max:500',
        'rider_count'       => 'require|integer|between:1,6',
        'vehicle_type'      => 'require|in:sedan,suv,van',
        'description'       => 'max:2000',
        'baggage_notes'     => 'max:500',
        'time_window_start' => 'require|date',
        'time_window_end'   => 'require|date',
        'tags'              => 'array|max:10',
    ];

    /**
     * Custom error messages.
     *
     * @var array<string, string>
     */
    protected $message = [
        'title.require'             => 'Listing title is required.',
        'title.min'                 => 'Title must be at least 5 characters.',
        'title.max'                 => 'Title must not exceed 200 characters.',
        'pickup_address.require'    => 'Pickup address is required.',
        'pickup_address.min'        => 'Pickup address must be at least 5 characters.',
        'pickup_address.max'        => 'Pickup address must not exceed 500 characters.',
        'dropoff_address.require'   => 'Drop-off address is required.',
        'dropoff_address.min'       => 'Drop-off address must be at least 5 characters.',
        'dropoff_address.max'       => 'Drop-off address must not exceed 500 characters.',
        'rider_count.require'       => 'Rider count is required.',
        'rider_count.integer'       => 'Rider count must be an integer.',
        'rider_count.between'       => 'Rider count must be between 1 and 6.',
        'vehicle_type.require'      => 'Vehicle type is required.',
        'vehicle_type.in'           => 'Vehicle type must be one of: sedan, suv, van.',
        'description.max'           => 'Description must not exceed 2000 characters.',
        'baggage_notes.max'         => 'Baggage notes must not exceed 500 characters.',
        'time_window_start.require' => 'Start time is required.',
        'time_window_start.date'    => 'Start time must be a valid date.',
        'time_window_end.require'   => 'End time is required.',
        'time_window_end.date'      => 'End time must be a valid date.',
        'tags.array'                => 'Tags must be an array.',
        'tags.max'                  => 'You may specify up to 10 tags.',
    ];

    /**
     * Validation scenes.
     *
     * @var array<string, string[]>
     */
    protected $scene = [
        'create' => [
            'title', 'pickup_address', 'dropoff_address', 'rider_count',
            'vehicle_type', 'description', 'baggage_notes',
            'time_window_start', 'time_window_end', 'tags',
        ],
        'update' => [
            'title', 'pickup_address', 'dropoff_address', 'rider_count',
            'vehicle_type', 'description', 'baggage_notes',
            'time_window_start', 'time_window_end', 'tags',
        ],
    ];

    /**
     * Override scene rules for update – all fields become optional.
     *
     * @return ListingValidate
     */
    public function sceneUpdate(): ListingValidate
    {
        return $this->only([
                'title', 'pickup_address', 'dropoff_address', 'rider_count',
                'vehicle_type', 'description', 'baggage_notes',
                'time_window_start', 'time_window_end', 'tags',
            ])
            ->remove('title', 'require')
            ->remove('pickup_address', 'require')
            ->remove('dropoff_address', 'require')
            ->remove('rider_count', 'require')
            ->remove('vehicle_type', 'require')
            ->remove('time_window_start', 'require')
            ->remove('time_window_end', 'require');
    }
}
