<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'メールアドレスを入力してください。',
            'email.email'    => 'メールアドレスの形式が正しくありません。',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        $messages = [
            Password::RESET_LINK_SENT => 'パスワード再設定リンクをメールでお送りしました。受信箱をご確認ください。',
            Password::INVALID_USER    => 'そのメールアドレスのアカウントが見つかりません。',
            Password::RESET_THROTTLED => 'しばらく時間をおいてから再度お試しください。',
        ];

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', $messages[$status]);
        }

        return back()->withErrors([
            'email' => $messages[$status] ?? __($status),
        ])->onlyInput('email');
    }
}
