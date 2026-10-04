<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'max:128', 'different:current_password',
                // Say everything that is still missing in one sentence, not one error at a time.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $missing = array_keys(array_filter([
                        'a lowercase letter' => ! preg_match('/\p{Ll}/u', $value),
                        'an uppercase letter' => ! preg_match('/\p{Lu}/u', $value),
                        'a number'           => ! preg_match('/\d/', $value),
                    ]));

                    if ($missing) {
                        $fail('Your new password still needs ' . $this->joinWords($missing) . '.');
                    }
                },
            ],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'current_password.required'         => 'Enter your current password.',
            'current_password.current_password' => 'That is not your current password. Check it and try again.',
            'password.required'                 => 'Enter a new password.',
            'password.min'                      => 'Your new password must be at least 8 characters long.',
            'password.max'                      => 'Your new password can be at most 128 characters long.',
            'password.different'                => 'Your new password must be different from your current one.',
            'password_confirmation.required'    => 'Re-enter your new password to confirm it.',
            'password_confirmation.same'        => 'The two passwords do not match.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        Activity::record($request->user(), 'password_changed', 'Changed your password');

        return back()->with('status', 'password-updated');
    }

    private function joinWords(array $items): string
    {
        $last = array_pop($items);

        return $items ? implode(', ', $items) . ' and ' . $last : $last;
    }
}
