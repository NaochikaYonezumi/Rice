<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.required'    => 'メールアドレスを入力してください。',
            'email.email'       => 'メールアドレスの形式が正しくありません。',
            'password.required' => 'パスワードを入力してください。',
            'password.confirmed' => 'パスワード(確認)が一致しません。',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // パスワード変更時は信頼デバイスも全失効 (二段階認証フローと整合)
                if (method_exists($user, 'trustedDevices')) {
                    $user->trustedDevices()->delete();
                }

                event(new PasswordReset($user));
            }
        );

        $messages = [
            Password::PASSWORD_RESET  => 'パスワードを変更しました。新しいパスワードでログインしてください。',
            Password::INVALID_TOKEN   => 'パスワード再設定リンクの有効期限が切れているか、無効です。再度メールから取得してください。',
            Password::INVALID_USER    => 'そのメールアドレスのアカウントが見つかりません。',
            Password::RESET_THROTTLED => 'しばらく時間をおいてから再度お試しください。',
        ];

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', $messages[$status]);
        }

        return back()->withInput($request->only('email'))
                     ->withErrors(['email' => $messages[$status] ?? __($status)]);
    }
}
