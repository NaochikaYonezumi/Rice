<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class TrustedDeviceService
{
    public function cookieName(): string
    {
        return (string) config('two_factor.trusted_device_cookie', 'rice_2fa_trust');
    }

    /**
     * Cookie の生トークンを取り出し、対応する有効な TrustedDevice があれば返す。
     */
    public function findValidForUser(User $user, ?string $rawCookieValue): ?TrustedDevice
    {
        if (!$rawCookieValue) {
            return null;
        }
        // Cookie 値は "<id>|<token>" 形式で発行する
        if (!str_contains($rawCookieValue, '|')) {
            return null;
        }
        [$id, $token] = explode('|', $rawCookieValue, 2);
        $device = TrustedDevice::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();
        if (!$device) {
            return null;
        }
        if (!Hash::check($token, $device->token_hash)) {
            return null;
        }
        $device->forceFill(['last_used_at' => now()])->save();
        return $device;
    }

    /**
     * 新しい信頼デバイスを登録し、レスポンスに乗せる Cookie を返す。
     */
    public function issue(User $user, ?string $userAgent): Cookie
    {
        $token = Str::random(64);
        $days = (int) config('two_factor.trusted_device_days', 30);
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addDays($days),
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            'last_used_at' => now(),
        ]);

        return cookie(
            name: $this->cookieName(),
            value: $device->id . '|' . $token,
            minutes: $days * 24 * 60,
            path: null,
            domain: null,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public function revokeAllFor(User $user): void
    {
        $user->trustedDevices()->delete();
    }
}
