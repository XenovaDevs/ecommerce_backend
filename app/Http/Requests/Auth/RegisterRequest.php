<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Messages\ValidationMessages;
use App\Support\Constants\SecurityConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:' . SecurityConstants::PASSWORD_MIN_LENGTH,
                Password::defaults(),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::get();
    }
}
