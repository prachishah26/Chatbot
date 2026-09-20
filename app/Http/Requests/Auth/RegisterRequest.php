<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates a new account before it is created.
 *
 * Note that `unique` tells the visitor when an address is already registered,
 * which the sign-in form deliberately avoids doing. Hiding it here would mean
 * accepting the submission and saying nothing until an email arrived, and this
 * application sends no mail; a sign-up form that silently fails is the worse
 * trade. The throttle on the route is what limits bulk probing.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'An account already exists for that email address.',
        ];
    }

    /**
     * Normalises the email so casing cannot create a near-duplicate account.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array{name: string, email: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
        ];
    }
}
