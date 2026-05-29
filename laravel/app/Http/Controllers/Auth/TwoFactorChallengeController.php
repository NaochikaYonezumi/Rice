<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\TotpService;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

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
        $usesTotp = $user->hasTotpEnabled();
        return view('auth.two-factor-challenge', [
            'maskedEmail' => $this->maskEmail($user->email),
            'usesTotp'    => $usesTotp,
        ]);
    }

    public function verify(
        Request $request,
        TwoFactorService $twoFactor,
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
        // TOTP 有効ユーザはまず TOTP 検証, ダメならフォールバックでメールコードも試す
        // (= 旧端末で TOTP アプリ未準備でメールが届いていたケースの救済).
        $ok = false;
        if ($user->hasTotpEnabled()) {
            $ok = $totp->verifyUser($user, $normalized);
        }
        if (!$ok) {
            $ok = $twoFactor->verifyCode($user, $normalized);
        }
        if (!$ok) {
            return back()->withErrors([
                'code' => '認証コードが正しくないか、期限切れです。',
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

    public function resend(
        Request $request,
        TwoFactorService $twoFactor,
    ): RedirectResponse {
        $pending = $this->pendingOrRedirect($request);
        if ($pending instanceof RedirectResponse) {
            return $pending;
        }
        /** @var User $user */
        $user = $pending['user'];

        $cooldown = (int) config('two_factor.resend_cooldown_seconds', 60);
        $cacheKey = 'two_factor_resend:' . $user->id;
        if (Cache::has($cacheKey)) {
            return back()->with('error', '少し待ってから再送してください。');
        }
        Cache::put($cacheKey, true, $cooldown);

        $code = $twoFactor->issueCode($user);
        try {
            Mail::to($user->email)->send(new TwoFactorCodeMail(
                user: $user,
                code: $code,
                lifetimeMinutes: (int) config('two_factor.code_lifetime_minutes', 10),
            ));
        } catch (\Throwable $e) {
            Log::error('2FAコード再送失敗', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'コード送信に失敗しました。時間をおいて再度お試しください。');
        }

        return back()->with('status', '新しい認証コードを送信しました。');
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

    protected function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        if (mb_strlen($local) <= 2) {
            $masked = mb_substr($local, 0, 1) . '*';
        } else {
            $masked = mb_substr($local, 0, 2) . str_repeat('*', max(1, mb_strlen($local) - 2));
        }
        return $masked . '@' . $domain;
    }
}
