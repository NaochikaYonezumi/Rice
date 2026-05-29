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
     *
     * 旧: google2fa-qrcode の getQRCodeInline (PNG, Imagick 拡張が必要)
     * 新: bacon/bacon-qr-code で SVG を直接書き出す. 純 PHP で動くので
     *     PHP 拡張に依存しない. data:image/svg+xml;base64,... を返す.
     */
    public function getQrCodeDataUrl(User $user, string $secret, int $size = 240): string
    {
        $otpUrl = $this->getOtpAuthUrl($user, $secret);
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 1),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            $svg = $writer->writeString($otpUrl);
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TOTP QR generation failed', ['error' => $e->getMessage()]);
            return '';
        }
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
