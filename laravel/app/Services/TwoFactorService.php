<?php

namespace App\Services;

use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TwoFactorService
{
    /**
     * 新規ワンタイムコードを発行し、平文を返す。
     * 既存の未消費コードは無効化する。
     */
    public function issueCode(User $user): string
    {
        $user->twoFactorCodes()
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $length = (int) config('two_factor.code_length', 6);
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_repeat('9', $length);
        $code = (string) random_int($min, $max);
        $code = str_pad($code, $length, '0', STR_PAD_LEFT);

        TwoFactorCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('two_factor.code_lifetime_minutes', 10)),
        ]);

        return $code;
    }

    /**
     * 平文コードを検証する。
     * - 一致 → 消費して true
     * - 不一致 → attempts をインクリメントし、上限超過で当該コードを消費扱いに
     */
    public function verifyCode(User $user, string $plainCode): bool
    {
        $plainCode = trim($plainCode);
        if ($plainCode === '') {
            return false;
        }

        $code = $user->twoFactorCodes()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$code) {
            return false;
        }

        $maxAttempts = (int) config('two_factor.max_attempts', 5);
        if ($code->attempts >= $maxAttempts) {
            $code->forceFill(['consumed_at' => now()])->save();
            return false;
        }

        if (Hash::check($plainCode, $code->code_hash)) {
            $code->forceFill(['consumed_at' => now()])->save();
            return true;
        }

        $code->increment('attempts');
        if ($code->attempts >= $maxAttempts) {
            $code->forceFill(['consumed_at' => now()])->save();
        }
        return false;
    }

    public function cleanupExpired(User $user): void
    {
        $user->twoFactorCodes()
            ->where('expires_at', '<', now()->subDay())
            ->delete();
    }
}
