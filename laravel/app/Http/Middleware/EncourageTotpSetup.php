<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 認証アプリ (TOTP) 未設定のユーザを setup ページに自動誘導する.
 *
 * 条件 (4 つすべて満たした時のみリダイレクト):
 *   - 認証済 (auth()->check())
 *   - GET リクエスト (POST/PUT/DELETE は素通しさせる. CSRF や AJAX を壊さないため)
 *   - HTML を返すルート (xhr / json / assets 系は除外)
 *   - TOTP 未確定 (totp_confirmed_at が NULL)
 *   - スキップしていない (totp_setup_skipped_at が NULL)
 *
 * 除外パス:
 *   - /two-factor/* (setup / confirm / skip / disable / challenge 自体)
 *   - /logout
 *   - /profile (= 元々プロフィールでも設定できるのでループ防止)
 *
 * リダイレクト先: /two-factor/totp/setup?reminder=1
 *   reminder=1 が立っている時だけ setup ビューに「あとで」 ボタンと案内バナーを出す.
 */
class EncourageTotpSetup
{
    /**
     * 除外パスのプレフィックス. ここに一致 (startsWith) すれば素通しする.
     *
     * @var string[]
     */
    protected array $exceptPrefixes = [
        'two-factor',
        'logout',
        'profile',
        'storage',
        'attachments',
        'api',
        'livewire',
        'up',         // health
        '_debugbar',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // 未認証はスルー (auth middleware が先に処理する想定)
        if (!auth()->check()) {
            return $next($request);
        }
        // GET 以外 (フォーム送信や AJAX) はスルー
        if (!$request->isMethod('GET')) {
            return $next($request);
        }
        // JSON / XHR レスポンス期待のリクエストはスルー
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }
        // 既に TOTP 確定済 or スキップ済 → 何もしない
        if ($user->totp_confirmed_at !== null || $user->totp_setup_skipped_at !== null) {
            return $next($request);
        }

        // 除外パス
        $path = ltrim($request->path(), '/');
        foreach ($this->exceptPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        // セットアップへ案内.
        // 戻り先は intended に元 URL を入れて, あとで設定が終わったらそこに飛ばせるようにする.
        $request->session()->put('url.intended', $request->fullUrl());
        return redirect()->route('totp.setup', ['reminder' => 1]);
    }
}
