<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Services\MicrosoftMailOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MailAccountOAuthController extends Controller
{
    public function __construct(protected MicrosoftMailOAuth $msOAuth) {}

    /**
     * Microsoft 365 OAuth 同意画面へ。
     * クエリ:
     *   - account_id (任意): 既存アカウントに紐付けて再認証する場合
     */
    public function redirectMicrosoft(Request $request): RedirectResponse
    {
        if (!$this->msOAuth->isConfigured()) {
            return redirect()->route('mail-accounts.index')
                ->with('error', 'Microsoft OAuth が未設定です。管理者に .env の MICROSOFT_MAIL_CLIENT_ID / SECRET / TENANT_ID 設定を依頼してください。');
        }

        $state = Str::random(40);
        $request->session()->put('ms_mail_oauth', [
            'state'      => $state,
            'account_id' => (int) $request->input('account_id', 0) ?: null,
            'user_id'    => (int) $request->user()->id,
        ]);

        return redirect()->away($this->msOAuth->authorizeUrl($state));
    }

    /**
     * Microsoft 365 OAuth コールバック.
     * 認可コードをトークンに交換し、MailAccount に保存する.
     */
    public function callbackMicrosoft(Request $request): RedirectResponse
    {
        $session = (array) $request->session()->pull('ms_mail_oauth', []);
        if (empty($session['state']) || $session['state'] !== $request->input('state')) {
            return redirect()->route('mail-accounts.index')
                ->with('error', 'OAuth コールバックの state 検証に失敗しました(セッション切れの可能性)。もう一度お試しください。');
        }
        if ($request->has('error')) {
            return redirect()->route('mail-accounts.index')
                ->with('error', 'Microsoft 認可がキャンセル/拒否されました: ' . $request->input('error_description', $request->input('error')));
        }
        if (!$request->filled('code')) {
            return redirect()->route('mail-accounts.index')->with('error', '認可コードが返されませんでした。');
        }

        try {
            $tokens = $this->msOAuth->exchangeCode($request->input('code'));
        } catch (\Throwable $e) {
            return redirect()->route('mail-accounts.index')
                ->with('error', 'トークン取得に失敗しました: ' . $e->getMessage());
        }

        $email = $this->msOAuth->emailFromIdToken($tokens['id_token'] ?? null) ?: '';

        // 既存アカウントへの再認証 or 新規アカウント作成
        $userId = (int) $session['user_id'];
        $accountId = (int) ($session['account_id'] ?? 0);
        if ($accountId > 0) {
            $account = MailAccount::where('id', $accountId)->where('user_id', $userId)->first();
            if (!$account) {
                return redirect()->route('mail-accounts.index')->with('error', '対象アカウントが見つかりません。');
            }
        } else {
            $account = new MailAccount([
                'user_id'        => $userId,
                'name'           => $email ?: 'Microsoft 365 アカウント',
                'email_address'  => $email,
                'is_active'      => true,
                // Microsoft 365 のデフォルトサーバ値を入れておく (XOAUTH2 でも host/port は必要)
                'inbox_protocol' => MailAccount::PROTOCOL_IMAP,
                'imap_host'      => 'outlook.office365.com',
                'imap_port'      => 993,
                'imap_encryption' => 'ssl',
                'imap_username'  => $email,
                'imap_folder'    => 'INBOX',
                'smtp_enabled'   => true,
                'smtp_host'      => 'smtp.office365.com',
                'smtp_port'      => 587,
                'smtp_encryption' => 'tls',
                'smtp_username'  => $email,
                'smtp_from_name' => $email,
            ]);
        }

        $account->auth_type           = MailAccount::AUTH_OAUTH_MICROSOFT;
        $account->oauth_provider      = MicrosoftMailOAuth::PROVIDER;
        $account->oauth_access_token  = $tokens['access_token'] ?? null;
        if (!empty($tokens['refresh_token'])) {
            $account->oauth_refresh_token = $tokens['refresh_token'];
        }
        if (!empty($tokens['expires_in'])) {
            $account->oauth_expires_at = now()->addSeconds((int) $tokens['expires_in']);
        }
        $account->oauth_scope = $tokens['scope'] ?? null;
        // OAuth に切替えたらユーザ名は OAuth から取れた email を使う (一致しないとサーバ側で拒否される)
        if ($email) {
            $account->imap_username = $email;
            $account->smtp_username = $email;
            if (empty($account->email_address)) $account->email_address = $email;
        }
        $account->save();

        return redirect()->route('mail-accounts.edit', $account)
            ->with('status', 'Microsoft 365 アカウントを連携しました。「保存」を押してホスト等を確認してください。');
    }
}
