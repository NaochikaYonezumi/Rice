<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialiteController extends Controller
{
    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! in_array($provider, config('auth.allowed_sso_providers', ['google']))) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from the provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! in_array($provider, config('auth.allowed_sso_providers', ['google']))) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', '認証に失敗しました。');
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            Auth::login($user);
            return redirect()->intended(route('emails.index', absolute: false));
        }

        // SSO Require Invitation logic
        if (config('auth.sso_require_invitation', true)) {
            $invitation = Invitation::where('email', $socialUser->getEmail())
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();

            if (! $invitation) {
                return redirect()->route('login')->with('error', 'このメールアドレスは招待されていません。管理者にお問い合わせください。');
            }

            // Create user from invitation
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? explode('@', $socialUser->getEmail())[0],
                'email' => $socialUser->getEmail(),
                'password' => bcrypt(Str::random(32)),
                'role' => $invitation->role,
                'email_verified_at' => now(),
            ]);

            $invitation->update(['accepted_at' => now()]);
            
            Auth::login($user);
            return redirect()->intended(route('emails.index', absolute: false));
        }

        // If signup is disabled and no invitation found (and not required, but let's be safe)
        if (! config('app.signup_enabled', false)) {
            return redirect()->route('login')->with('error', '新規登録は現在停止されています。');
        }

        // Auto-create user if invitation not required
        $user = User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? explode('@', $socialUser->getEmail())[0],
            'email' => $socialUser->getEmail(),
            'password' => bcrypt(Str::random(32)),
            'role' => 'member',
            'email_verified_at' => now(),
        ]);

        Auth::login($user);
        return redirect()->intended(route('emails.index', absolute: false));
    }
}
