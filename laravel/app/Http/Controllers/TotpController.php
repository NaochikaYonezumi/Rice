<?php

namespace App\Http\Controllers;

use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 認証アプリ (TOTP) のセットアップ / 確定 / 無効化を扱う.
 *
 * フロー:
 *   1. GET  /two-factor/totp/setup     → シークレット生成 + QR表示 (まだ DB には保存しない)
 *   2. POST /two-factor/totp/confirm   → アプリで生成したコードを検証. OK なら DB 保存 + confirmed_at セット
 *   3. POST /two-factor/totp/disable   → secret / confirmed_at をクリア
 *
 * セットアップ途中のシークレットはセッションで保持し, confirm 時に DB に書く.
 * これによって「QR 表示しただけで保存」の中途半端な状態を避ける.
 */
class TotpController extends Controller
{
    public function __construct(protected TotpService $totp) {}

    /** セットアップ画面: シークレット生成 + QR 表示. */
    public function setup(Request $request): View
    {
        $user = $request->user();

        // セッションにシークレットが無ければ新しく生成. 既にあれば再利用 (リロード対策).
        $secret = $request->session()->get('totp.setup_secret');
        if (!is_string($secret) || $secret === '') {
            $secret = $this->totp->generateSecret();
            $request->session()->put('totp.setup_secret', $secret);
        }

        return view('auth.totp-setup', [
            'secret'    => $secret,
            'qrDataUrl' => $this->totp->getQrCodeDataUrl($user, $secret),
            'manualUri' => $this->totp->getOtpAuthUrl($user, $secret),
            'alreadyEnabled' => $user->hasTotpEnabled(),
            // ?reminder=1 で来た時だけバナー + 「あとで」 ボタンを出す
            'isReminder' => $request->boolean('reminder'),
        ]);
    }

    /** セットアップ確定: コード検証 → 保存 → confirmed_at. */
    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => '認証アプリに表示された 6 桁コードを入力してください.',
        ]);

        $secret = $request->session()->get('totp.setup_secret');
        if (!is_string($secret) || $secret === '') {
            return redirect()->route('totp.setup')
                ->withErrors(['code' => 'セットアップ情報が見つかりません. もう一度やり直してください.']);
        }

        $normalized = preg_replace('/\s+/', '', $data['code']);
        if (!$this->totp->verifyPlainSecret($secret, $normalized)) {
            return back()->withErrors([
                'code' => 'コードが正しくないか, 時刻ずれの可能性があります. もう一度試してください.',
            ]);
        }

        $user = $request->user();
        $user->forceFill([
            'totp_secret'           => $secret,      // model casts で encrypted で保存される
            'totp_confirmed_at'     => now(),
            'totp_setup_skipped_at' => null,         // 確定したのでスキップフラグはクリア
        ])->save();

        $request->session()->forget('totp.setup_secret');

        return redirect()->intended(route('profile.edit', absolute: false))->with(
            'status',
            '認証アプリでの二段階認証を有効化しました.'
        );
    }

    /** TOTP 無効化. */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'totp_secret'           => null,
            'totp_confirmed_at'     => null,
            'totp_setup_skipped_at' => null,   // 無効化したら次回ログイン後に再案内
        ])->save();
        $request->session()->forget('totp.setup_secret');

        return redirect()->route('profile.edit')->with(
            'status',
            '認証アプリの二段階認証を無効化しました.'
        );
    }

    /**
     * 「あとで設定する」: totp_setup_skipped_at を打って自動誘導を止める.
     * 設定し直したい時はプロフィール画面から再度 setup できる.
     */
    public function skip(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'totp_setup_skipped_at' => now(),
        ])->save();
        $request->session()->forget('totp.setup_secret');

        // intended があればそこへ. なければメール画面.
        return redirect()->intended(route('emails.index', absolute: false))
            ->with('status', '認証アプリの設定をあとで行うようにしました. プロフィール画面からいつでも有効化できます.');
    }
}
