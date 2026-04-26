<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Models\SsoSetting;
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
        $settings = SsoSetting::getSettings();
        if (!$settings->is_enabled) {
            return redirect()->route('login')->with('error', 'SSOログインは現在無効です。');
        }

        if (! in_array($provider, config('auth.allowed_sso_providers', ['google']))) {
            abort(404);
        }

        $this->configureProvider($provider, $settings);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from the provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        $settings = SsoSetting::getSettings();
        
        if (! in_array($provider, config('auth.allowed_sso_providers', ['google']))) {
            abort(404);
        }

        $this->configureProvider($provider, $settings);

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
        if ($settings->require_invitation) {
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

        // If signup is disabled and no invitation found
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

    /**
     * Configure Socialite provider at runtime.
     */
    private function configureProvider(string $provider, SsoSetting $settings): void
    {
        if ($provider === 'google') {
            config([
                'services.google.client_id' => $settings->google_client_id,
                'services.google.client_secret' => $settings->google_client_secret,
                'services.google.redirect' => $settings->google_redirect_uri,
            ]);
        }
    }
}
