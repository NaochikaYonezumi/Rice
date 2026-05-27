<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorRecoveryCodesController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('two_factor.fresh_recovery_codes');

        // 平文コードがセッションに無い場合は再生成画面ではなく、ホームへ戻す
        // (生成済みコードは平文で再表示できないため)
        if (!is_array($codes) || empty($codes)) {
            return redirect()->route('emails.index')->with(
                'status',
                'リカバリーコードを再表示するには再生成が必要です。プロフィール画面から再生成してください。'
            );
        }

        return view('auth.two-factor-recovery-codes', [
            'codes' => $codes,
        ]);
    }

    /**
     * ユーザがリカバリーコードを安全に保管した旨を確認した。
     */
    public function acknowledge(Request $request): RedirectResponse
    {
        $request->validate(['ack' => ['accepted']]);

        $request->session()->forget('two_factor.fresh_recovery_codes');
        $request->session()->forget('two_factor.regenerate_required');

        return redirect()->intended(route('emails.index', absolute: false));
    }

    /**
     * リカバリーコードを再生成する。新しい平文コードはセッションに乗せて show へリダイレクト。
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $codes = $user->generateRecoveryCodes();

        $request->session()->put('two_factor.fresh_recovery_codes', $codes);

        return redirect()->route('two-factor.recovery-codes.show');
    }
}
