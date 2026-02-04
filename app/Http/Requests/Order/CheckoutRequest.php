<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Messages\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @ai-context CheckoutRequest validates checkout data.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => ['required', 'array'],
            'shipping_address.name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:50'],
            'shipping_address.address' => ['required', 'string', 'max:500'],
            'shipping_address.address_line_2' => ['nullable', 'string', 'max:500'],
            'shipping_address.city' => ['required', 'string', 'max:255'],
            'shipping_address.state' => ['required', 'string', 'max:255'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
            'shipping_address.country' => ['required', 'string', 'max:255'],

            'billing_address' => ['required', 'array'],
            'billing_address.name' => ['required', 'string', 'max:255'],
            'billing_address.phone' => ['required', 'string', 'max:50'],
            'billing_address.address' => ['required', 'string', 'max:500'],
            'billing_address.address_line_2' => ['nullable', 'string', 'max:500'],
            'billing_address.city' => ['required', 'string', 'max:255'],
            'billing_address.state' => ['required', 'string', 'max:255'],
            'billing_address.postal_code' => ['required', 'string', 'max:20'],
            'billing_address.country' => ['required', 'string', 'max:255'],

            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'in:mercadopago,cash'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::get();
    }
}
