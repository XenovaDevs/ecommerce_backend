<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Messages\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @ai-context Request validation for replying to a contact message.
 *             Admin-only endpoint for responding to customer inquiries.
 */
class ReplyContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'admin_reply' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return array_merge(
            ValidationMessages::get(),
            [
                'admin_reply.min' => 'The reply must be at least 10 characters.',
                'admin_reply.max' => 'The reply must not exceed 5000 characters.',
            ]
        );
    }
}
