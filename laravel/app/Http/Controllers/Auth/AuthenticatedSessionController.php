<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        Request $request,
        TwoFactorService $twoFactor,
        TrustedDeviceService $trustedDevices,
    ): RedirectResponse {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'メールアドレスを入力してください。',
            'email.email'    => 'メールアドレスの形式が正しくありません。',
            'password.required' => 'パスワードを入力してください。',
        ]);

        if (!Auth::validate($credentials)) {
            return back()->withErrors([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->first();
        $remember = $request->boolean('remember');

        // 信頼デバイス Cookie が有効なら 2FA をスキップ
        $cookieName = $trustedDevices->cookieName();
        $rawCookie = $request->cookie($cookieName);
        if ($trustedDevices->findValidForUser($user, $rawCookie)) {
            Auth::login($user, $remember);
            $request->session()->regenerate();
            return redirect()->intended(route('emails.index', absolute: false));
        }

        // pending 2FA セッションを作成
        $request->session()->put('pending_2fa', [
            'user_id' => $user->id,
            'remember' => $remember,
            'intended_url' => $request->session()->get('url.intended'),
            'expires_at' => now()->addMinutes((int) config('two_factor.pending_session_lifetime_minutes', 15))->timestamp,
        ]);

        // コード発行 + メール送信
        $code = $twoFactor->issueCode($user);
        try {
            Mail::to($user->email)->send(new TwoFactorCodeMail(
                user: $user,
                code: $code,
                lifetimeMinutes: (int) config('two_factor.code_lifetime_minutes', 10),
            ));
        } catch (\Throwable $e) {
            Log::error('2FAコード送信失敗', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('two-factor.challenge');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
