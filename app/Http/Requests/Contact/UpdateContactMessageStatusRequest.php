<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Domain\Enums\ContactMessageStatus;
use App\Messages\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @ai-context Request validation for updating contact message status.
 *             Admin-only endpoint for managing message workflow.
 */
class UpdateContactMessageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ContactMessageStatus::values())],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::get();
    }
}
