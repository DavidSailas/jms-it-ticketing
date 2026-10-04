<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /** Tidy the email before it is checked, so " Name@Company.com " just works. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Enter your full name.',
            'name.max'       => 'Your name can be at most 255 characters.',
            'email.required' => 'Enter your email address.',
            'email.email'    => 'Enter a valid email address, like name@company.com.',
            'email.max'      => 'Your email address can be at most 255 characters.',
            'email.unique'   => 'Another account already uses this email address.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
