<?php

declare(strict_types=1);

namespace App\Http\Requests\Coupon;

use App\Messages\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @ai-context ApplyCouponRequest validates applying a coupon to the cart.
 *             Ensures the coupon code is provided and meets basic format requirements.
 */
class ApplyCouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Coupons can be applied by both guests and authenticated users.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/i', // Alphanumeric, underscore, and dash only
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return array_merge(
            ValidationMessages::get(),
            [
                'code.regex' => 'The coupon code format is invalid. Only letters, numbers, dashes, and underscores are allowed.',
            ]
        );
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'code' => 'coupon code',
        ];
    }
}
