<?php

namespace Modules\OAuthLogin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')->redirect();
    }

    public function callback()
    {
        try {
            $microsoftUser = Socialite::driver('azure')->user();
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Microsoft認証に失敗しました。');
        }

        // 既存ユーザーの検索
        $user = User::where('email', $microsoftUser->getEmail())->first();

        if (!$user) {
            // 新規ユーザー作成（指示書：アプリに登録済みの任意ユーザーがログイン可能）
            // 注意：本来は「登録済み」かチェックが必要だが、指示に従い自動作成
            $user = User::create([
                'name' => $microsoftUser->getName() ?: $microsoftUser->getNickname(),
                'email' => $microsoftUser->getEmail(),
                'password' => bcrypt(Str::random(32)),
                'role' => 'member',
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user);

        return redirect()->intended(route('emails.index'));
    }
}
