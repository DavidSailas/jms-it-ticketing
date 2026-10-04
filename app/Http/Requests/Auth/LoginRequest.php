<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required'    => 'Enter your email address or username.',
            'login.max'         => 'That is too long to be an email address or username.',
            'password.required' => 'Enter your password.',
        ];
    }

    /**
     * Sign in with either an email address or a username.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = Str::lower(trim($this->string('login')->toString()));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $this->string('password')->toString()], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'credentials' => 'The email/username or password you entered is incorrect. Please check and try again.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /** @throws ValidationException */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $wait = $seconds > 60 ? ceil($seconds / 60) . ' minute(s)' : $seconds . ' second(s)';

        throw ValidationException::withMessages([
            'credentials' => "Too many sign-in attempts. Please try again in {$wait}.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')->toString()) . '|' . $this->ip());
    }
}
