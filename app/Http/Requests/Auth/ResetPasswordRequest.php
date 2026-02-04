<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Messages\ValidationMessages;
use App\Support\Constants\SecurityConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * @ai-context ResetPasswordRequest validates password reset endpoint input.
 *             Follows Laravel Form Request pattern with strong password rules.
 */
class ResetPasswordRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(SecurityConstants::PASSWORD_MIN_LENGTH)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
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
