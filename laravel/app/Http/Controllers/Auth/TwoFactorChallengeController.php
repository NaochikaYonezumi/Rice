<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 2FA チャレンジ画面 (TOTP のみ).
 * メール認証コード方式は廃止. リカバリーコード救済は引き続き利用可能.
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingOrRedirect($request);
        if ($pending instanceof RedirectResponse) {
            return $pending;
        }
        /** @var User $user */
        $user = $pending['user'];
        // TOTP 未設定でこの画面に来てしまったら自動で setup へ.
        if (!$user->hasTotpEnabled()) {
            Auth::login($user, (bool) ($pending['remember'] ?? false));
            $request->session()->regenerate();
            $request->session()->forget('pending_2fa');
            return redirect()->route('totp.setup');
        }
        return view('auth.two-factor-challenge');
    }

    public function verify(
        Request $request,
        TotpService $totp,
        TrustedDeviceService $trustedDevices,
    ): RedirectResponse {
        $pending = $this->pendingOrRedirect($request);
        if ($pending instanceof RedirectResponse) {
            return $pending;
        }
        /** @var User $user */
        $user = $pending['user'];

        $data = $request->validate([
            'code' => ['required', 'string'],
            'trust_device' => ['nullable', 'boolean'],
        ], [
            'code.required' => '認証コードを入力してください。',
        ]);

        $normalized = preg_replace('/\s+/', '', $data['code']);
        if (!$user->hasTotpEnabled() || !$totp->verifyUser($user, $normalized)) {
            return back()->withErrors([
                'code' => '認証コードが正しくありません。',
            ]);
        }

        return $this->completeLogin(
            request: $request,
            user: $user,
            remember: (bool) ($pending['remember'] ?? false),
            intendedUrl: $pending['intended_url'] ?? null,
            trustDevice: (bool) ($data['trust_device'] ?? false),
            trustedDevices: $trustedDevices,
        );
    }

    public function recovery(
        Request $request,
        TrustedDeviceService $trustedDevices,
    ): RedirectResponse {
        $pending = $this->pendingOrRedirect($request);
        if ($pending instanceof RedirectResponse) {
            return $pending;
        }
        /** @var User $user */
        $user = $pending['user'];

        $data = $request->validate([
            'recovery_code' => ['required', 'string'],
        ], [
            'recovery_code.required' => 'リカバリーコードを入力してください。',
        ]);

        if (!$user->consumeRecoveryCode($data['recovery_code'])) {
            return back()->withErrors([
                'recovery_code' => 'リカバリーコードが正しくありません。',
            ]);
        }

        // リカバリーコード使用時は信頼デバイスを全て revoke (安全側)
        $trustedDevices->revokeAllFor($user);

        // 再生成画面を強制表示するためフラグを立てる
        $request->session()->put('two_factor.regenerate_required', true);

        return $this->completeLogin(
            request: $request,
            user: $user,
            remember: (bool) ($pending['remember'] ?? false),
            intendedUrl: $pending['intended_url'] ?? null,
            trustDevice: false,
            trustedDevices: $trustedDevices,
        );
    }

    /**
     * pending 2FA セッションを読み込み、無効なら /login にリダイレクトを返す。
     */
    protected function pendingOrRedirect(Request $request): array|RedirectResponse
    {
        $pending = $request->session()->get('pending_2fa');
        if (!is_array($pending) || empty($pending['user_id'])) {
            return redirect()->route('login');
        }
        if (!empty($pending['expires_at']) && now()->timestamp > (int) $pending['expires_at']) {
            $request->session()->forget('pending_2fa');
            return redirect()->route('login')->withErrors([
                'email' => 'ログインセッションの有効期限が切れました。もう一度ログインしてください。',
            ]);
        }
        $user = User::find($pending['user_id']);
        if (!$user) {
            $request->session()->forget('pending_2fa');
            return redirect()->route('login');
        }
        return [
            'user' => $user,
            'remember' => $pending['remember'] ?? false,
            'intended_url' => $pending['intended_url'] ?? null,
        ];
    }

    protected function completeLogin(
        Request $request,
        User $user,
        bool $remember,
        ?string $intendedUrl,
        bool $trustDevice,
        TrustedDeviceService $trustedDevices,
    ): RedirectResponse {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget('pending_2fa');

        $destination = $intendedUrl ?: route('emails.index', absolute: false);
        $response = redirect()->to($destination);

        if ($trustDevice) {
            $cookie = $trustedDevices->issue($user, $request->userAgent());
            $response = $response->withCookie($cookie);
        }

        return $response;
    }
}
