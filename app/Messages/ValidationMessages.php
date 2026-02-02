<?php

declare(strict_types=1);

namespace App\Messages;

/**
 * @ai-context Custom validation messages for Laravel form requests.
 *             Use these in FormRequest classes for consistent messaging.
 * @ai-dependencies None - standalone constants class
 * @ai-flow FormRequest classes include these messages via messages() method
 */
final class ValidationMessages
{
    /**
     * Get all custom validation messages.
     *
     * @return array<string, string>
     */
    public static function get(): array
    {
        return [
            'required' => 'The :attribute field is required',
            'email' => 'Please provide a valid email address',
            'min' => [
                'string' => 'The :attribute must be at least :min characters',
                'numeric' => 'The :attribute must be at least :min',
                'array' => 'The :attribute must have at least :min items',
            ],
            'max' => [
                'string' => 'The :attribute must not exceed :max characters',
                'numeric' => 'The :attribute must not exceed :max',
                'array' => 'The :attribute must not have more than :max items',
            ],
            'unique' => 'The :attribute has already been taken',
            'confirmed' => 'The :attribute confirmation does not match',
            'exists' => 'The selected :attribute is invalid',
            'numeric' => 'The :attribute must be a number',
            'integer' => 'The :attribute must be an integer',
            'boolean' => 'The :attribute must be true or false',
            'array' => 'The :attribute must be an array',
            'date' => 'The :attribute must be a valid date',
            'after' => 'The :attribute must be a date after :date',
            'before' => 'The :attribute must be a date before :date',
            'between' => [
                'numeric' => 'The :attribute must be between :min and :max',
                'string' => 'The :attribute must be between :min and :max characters',
            ],
            'in' => 'The selected :attribute is invalid',
            'not_in' => 'The selected :attribute is invalid',
            'image' => 'The :attribute must be an image',
            'mimes' => 'The :attribute must be a file of type: :values',
            'url' => 'The :attribute must be a valid URL',
            'regex' => 'The :attribute format is invalid',
            'alpha' => 'The :attribute may only contain letters',
            'alpha_num' => 'The :attribute may only contain letters and numbers',
            'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores',
            'size' => [
                'numeric' => 'The :attribute must be :size',
                'string' => 'The :attribute must be :size characters',
                'array' => 'The :attribute must contain :size items',
            ],
            'digits' => 'The :attribute must be :digits digits',
            'digits_between' => 'The :attribute must be between :min and :max digits',
            'gt' => [
                'numeric' => 'The :attribute must be greater than :value',
            ],
            'gte' => [
                'numeric' => 'The :attribute must be greater than or equal to :value',
            ],
            'lt' => [
                'numeric' => 'The :attribute must be less than :value',
            ],
            'lte' => [
                'numeric' => 'The :attribute must be less than or equal to :value',
            ],
        ];
    }

    /**
     * Get custom attribute names for validation.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'email' => 'email address',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'phone' => 'phone number',
            'postal_code' => 'postal code',
            'category_id' => 'category',
            'product_id' => 'product',
            'variant_id' => 'variant',
            'quantity' => 'quantity',
            'shipping_address_id' => 'shipping address',
            'billing_address_id' => 'billing address',
        ];
    }
}
