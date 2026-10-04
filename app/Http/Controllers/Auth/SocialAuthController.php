<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google' => 'Google', 'facebook' => 'Facebook'];

    public function redirect(string $provider)
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);

        // Friendly message instead of a crash when the app keys are not in .env yet.
        if (! config("services.$provider.client_id") || ! config("services.$provider.client_secret")) {
            return redirect()->route('login')->withErrors([
                'social' => self::PROVIDERS[$provider] . ' sign-in is not available yet. Please use your email and password.',
            ]);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors([
                'social' => self::PROVIDERS[$provider] . ' sign-in was cancelled or failed. Please try again.',
            ]);
        }

        if (! $social->getEmail()) {
            return redirect()->route('login')->withErrors([
                'social' => 'Your ' . self::PROVIDERS[$provider] . ' account did not share an email address, so we could not sign you in.',
            ]);
        }

        // Accounts are created by an administrator, so social sign-in only works for existing users.
        $user = User::where('email', strtolower($social->getEmail()))->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'social' => 'No account was found for ' . $social->getEmail() . '. Please ask your JMS One IT administrator to create one for you.',
            ]);
        }

        $user->update(['provider' => $provider, 'provider_id' => $social->getId()]);

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
