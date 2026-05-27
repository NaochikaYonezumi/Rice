<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecoveryCodesAcknowledged
{
    /**
     * リカバリーコード未生成の認証済みユーザは、コード表示画面へ強制リダイレクトする。
     * また、リカバリーコードを使ってログインした場合も、新しいコードへの再生成を促す。
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // ホワイトリスト: これらのルートは常に通す
        $allowed = [
            'two-factor.recovery-codes.show',
            'two-factor.recovery-codes.acknowledge',
            'two-factor.recovery-codes.regenerate',
            'logout',
        ];
        $routeName = optional($request->route())->getName();
        if (in_array($routeName, $allowed, true)) {
            return $next($request);
        }

        // GET 以外は走査しない (POST の途中で中断するとデータが失われる)
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $needsInitialCodes = $user->two_factor_recovery_generated_at === null;
        $needsRegenerate = (bool) $request->session()->get('two_factor.regenerate_required', false);

        if ($needsInitialCodes || $needsRegenerate) {
            $codes = $user->generateRecoveryCodes();
            $request->session()->put('two_factor.fresh_recovery_codes', $codes);
            $request->session()->forget('two_factor.regenerate_required');
            return redirect()->route('two-factor.recovery-codes.show');
        }

        return $next($request);
    }
}
