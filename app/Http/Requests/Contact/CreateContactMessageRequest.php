<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Messages\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @ai-context Request validation for creating a contact message.
 *             Validates customer inquiries from the public contact form.
 */
class CreateContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return array_merge(
            ValidationMessages::get(),
            [
                'message.min' => 'The message must be at least 10 characters.',
                'message.max' => 'The message must not exceed 5000 characters.',
            ]
        );
    }
}
