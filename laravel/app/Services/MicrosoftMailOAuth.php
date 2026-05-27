<?php

namespace App\Services;

use App\Models\MailAccount;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft 365 メール用 OAuth2 (XOAUTH2 IMAP/SMTP) のトークン取得・更新を担う。
 *
 * Azure App Registration 側で必要な API permissions:
 *   - Office 365 Exchange Online > IMAP.AccessAsUser.All (Delegated)
 *   - Office 365 Exchange Online > SMTP.Send             (Delegated)
 *   - Microsoft Graph             > offline_access        (Delegated)
 *   - Microsoft Graph             > User.Read / email     (Delegated, ユーザ情報用)
 */
class MicrosoftMailOAuth
{
    public const PROVIDER = 'microsoft';

    /**
     * IMAP/SMTP XOAUTH2 で必要なスコープ.
     * Outlook のリソース URL 配下のスコープを要求するのがポイント.
     */
    public const SCOPES = [
        'https://outlook.office.com/IMAP.AccessAsUser.All',
        'https://outlook.office.com/SMTP.Send',
        'offline_access',
        'openid',
        'email',
        'profile',
    ];

    public function clientId(): string
    {
        return (string) config('services.microsoft_mail.client_id');
    }

    public function clientSecret(): string
    {
        return (string) config('services.microsoft_mail.client_secret');
    }

    public function tenant(): string
    {
        return (string) (config('services.microsoft_mail.tenant') ?: 'common');
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = config('services.microsoft_mail.redirect_uri');
        if ($configured) return (string) $configured;
        return rtrim(config('app.url'), '/') . '/mail-accounts/oauth/microsoft/callback';
    }

    /**
     * 認可画面の URL を組み立てる. state は CSRF 兼戻り先識別子.
     */
    public function authorizeUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri(),
            'response_mode' => 'query',
            'scope'         => implode(' ', self::SCOPES),
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
        return "https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/authorize?{$params}";
    }

    /**
     * 認可コードをトークンに交換する.
     * 戻り値: ['access_token','refresh_token','expires_in','scope','id_token']
     */
    public function exchangeCode(string $code): array
    {
        $client = new Client(['timeout' => 15]);
        $res = $client->post("https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/token", [
            'form_params' => [
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->redirectUri(),
                'scope'         => implode(' ', self::SCOPES),
            ],
            'http_errors' => true,
        ]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    /**
     * refresh_token で access_token を更新する.
     */
    public function refresh(string $refreshToken): array
    {
        $client = new Client(['timeout' => 15]);
        $res = $client->post("https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/token", [
            'form_params' => [
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $refreshToken,
                'grant_type'    => 'refresh_token',
                'scope'         => implode(' ', self::SCOPES),
            ],
            'http_errors' => true,
        ]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    /**
     * id_token から email を取り出す (JWT decode・署名検証はしない).
     * 認可コード交換のレスポンスに含まれる id_token を渡す.
     */
    public function emailFromIdToken(?string $idToken): ?string
    {
        if (!$idToken) return null;
        $parts = explode('.', $idToken);
        if (count($parts) < 2) return null;
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($payload)) return null;
        return $payload['email'] ?? $payload['preferred_username'] ?? $payload['upn'] ?? null;
    }

    /**
     * MailAccount のトークンが期限切れなら refresh して保存し、有効な access_token を返す.
     */
    public function ensureValidAccessToken(MailAccount $account): ?string
    {
        if (!$account->isOAuth()) return null;
        if ($account->isAccessTokenValid()) return $account->oauth_access_token;
        if (!$account->oauth_refresh_token) return $account->oauth_access_token; // どうにもならない
        try {
            $tokens = $this->refresh($account->oauth_refresh_token);
            $account->oauth_access_token  = $tokens['access_token'] ?? $account->oauth_access_token;
            // refresh_token は (rotating の場合) 新しい方で上書き、無ければ既存維持
            if (!empty($tokens['refresh_token'])) {
                $account->oauth_refresh_token = $tokens['refresh_token'];
            }
            if (!empty($tokens['expires_in'])) {
                $account->oauth_expires_at = now()->addSeconds((int) $tokens['expires_in']);
            }
            if (!empty($tokens['scope'])) {
                $account->oauth_scope = (string) $tokens['scope'];
            }
            $account->save();
            return $account->oauth_access_token;
        } catch (\Throwable $e) {
            Log::error('[ms-oauth] refresh失敗 account=' . $account->id . ': ' . $e->getMessage());
            return $account->oauth_access_token; // 既存を返す (期限切れの可能性あり)
        }
    }
}
