<?php

namespace App\Services;

use App\Models\User;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * 認証アプリ (Google Authenticator / Authy / Microsoft Authenticator 等) との
 * TOTP (Time-based One-Time Password) 連携を担当するサービス.
 *
 * シークレットは User モデルの casts で 'encrypted' 指定されており,
 * DB には Crypt 経由で保存される.
 */
class TotpService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
        // 既定の検証ウィンドウ (前後 1 = 30秒ぶれ許容). 不要なら 0 に.
        // ユーザ体験を優先して 1 を維持.
    }

    /** 新規シークレット (Base32) を生成. */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * otpauth:// URL を生成. これを QR エンコードすれば認証アプリで読める.
     */
    public function getOtpAuthUrl(User $user, string $secret): string
    {
        $issuer = config('app.name', 'Rice');
        $label  = $user->email;
        return $this->google2fa->getQRCodeUrl(
            $issuer,
            $label,
            $secret
        );
    }

    /**
     * QR コード画像を data: URL で返す. <img src="..."> にそのまま渡せる.
     * google2fa-qrcode はバックエンドに BaconQrCode を使う.
     */
    public function getQrCodeDataUrl(User $user, string $secret, int $size = 220): string
    {
        $issuer = config('app.name', 'Rice');
        $label  = $user->email;
        // getQRCodeInline は PNG (base64) を data:image/png;base64,... の形で返す
        return $this->google2fa->getQRCodeInline($issuer, $label, $secret, $size);
    }

    /**
     * ユーザの保存済みシークレットで code を検証する.
     * window=1 で前後 30 秒ずつのドリフトを許容.
     */
    public function verifyUser(User $user, string $code): bool
    {
        $code = trim($code);
        if ($code === '' || empty($user->totp_secret)) return false;
        try {
            return (bool) $this->google2fa->verifyKey($user->totp_secret, $code, 1);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * セットアップ中の (まだ confirmed_at が NULL の) シークレットを引数で受けて検証する.
     */
    public function verifyPlainSecret(string $secret, string $code): bool
    {
        $code = trim($code);
        if ($code === '' || $secret === '') return false;
        try {
            return (bool) $this->google2fa->verifyKey($secret, $code, 1);
        } catch (\Throwable) {
            return false;
        }
    }
}
