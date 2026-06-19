<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validated credentials for authentication.
     *
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return $this->safe()->only(['email', 'password']);
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }

    public function throttleKey(): string
    {
        return Str::lower((string) $this->string('email')->trim()).'|'.$this->ip();
    }
}
