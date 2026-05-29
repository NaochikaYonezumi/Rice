<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     *
     * 2FA は TOTP (認証アプリ) のみ. 旧メール 2FA は廃止.
     *   - TOTP 設定済みユーザ           → /two-factor/challenge (TOTP 入力)
     *   - TOTP 未設定 (新規 / 移行中)   → そのままログイン → ミドルウェアで /two-factor/totp/setup へ誘導
     */
    public function store(
        Request $request,
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

        // 資格情報のみ検証 (まだセッション化しない)
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

        // TOTP 未設定ユーザは 2FA チャレンジ無しでログイン → EncourageTotpSetup
        // ミドルウェアが /two-factor/totp/setup に誘導する.
        if (!$user->hasTotpEnabled()) {
            Auth::login($user, $remember);
            $request->session()->regenerate();
            return redirect()->intended(route('emails.index', absolute: false));
        }

        // TOTP 設定済みユーザは TOTP コード入力チャレンジへ.
        $request->session()->put('pending_2fa', [
            'user_id' => $user->id,
            'remember' => $remember,
            'intended_url' => $request->session()->get('url.intended'),
            'expires_at' => now()->addMinutes((int) config('two_factor.pending_session_lifetime_minutes', 15))->timestamp,
        ]);

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
