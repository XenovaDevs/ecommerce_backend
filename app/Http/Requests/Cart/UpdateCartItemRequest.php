<?php

declare(strict_types=1);

namespace App\Http\Requests\Cart;

use App\Messages\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @ai-context UpdateCartItemRequest validates updating cart item quantity.
 */
class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::get();
    }
}
