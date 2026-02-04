<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Messages\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @ai-context ForgotPasswordRequest validates forgot password endpoint input.
 *             Follows Laravel Form Request pattern for clean validation.
 */
class ForgotPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ValidationMessages::get();
    }
}
