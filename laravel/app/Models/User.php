<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Mass assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'signature',
        'email',
        'password',
        'role',
        'email_verified_at',
    ];

    /**
     * 署名の解決順 (2026-05 更新: プロフィール画面の単一 signature は廃止し、
     *   UserSignature (compose-window の「署名挿入」picker) で複数管理する仕様に統一):
     *   1. UserSignature の is_default レコード (compose-window で「既定」マークされたもの)
     *   2. 旧 users.signature 列 (互換性のため残置. 既存データを上書きしない)
     *   3. AiSetting の agent_signature (全体既定)
     *   4. ハードコード フォールバック
     */
    public function resolvedSignature(): string
    {
        // (1) ユーザが UserSignature で「既定」設定した署名を最優先で使う
        try {
            $defaultSig = \App\Models\UserSignature::where('user_id', $this->id)
                ->where('is_default', true)
                ->orderByDesc('updated_at')
                ->first();
            if ($defaultSig && !empty($defaultSig->content)) return $defaultSig->content;
        } catch (\Throwable) {}
        // (2) 旧プロフィール画面で設定した signature (列は残してあるので、未移行ユーザは引き続き使う)
        if (!empty($this->signature)) return $this->signature;
        // (3) AI 設定の agent 既定署名
        try {
            $ai = \App\Models\AiSetting::getSettings();
            if (!empty($ai->agent_signature)) return $ai->agent_signature;
        } catch (\Throwable) {}
        // (4) ハードコード
        $display = $this->display_name ?: $this->name;
        return "---\nPaperCutサポート窓口\n{$display}";
    }

    public function resolvedDisplayName(): string
    {
        return $this->display_name ?: $this->name ?: 'ユーザー';
    }

    /**
     * Hidden attributes for arrays/JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Check if user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_recovery_generated_at' => 'datetime',
        ];
    }

    public function twoFactorCodes(): HasMany
    {
        return $this->hasMany(TwoFactorCode::class);
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * 新しいリカバリーコードを生成して保存し、平文配列を返す。
     * 返した平文は1度だけユーザーに見せる用途。DBにはハッシュのみ保存する。
     */
    public function generateRecoveryCodes(): array
    {
        $count = (int) config('two_factor.recovery_code_count', 8);
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < $count; $i++) {
            $code = Str::upper(Str::random(5)) . '-' . Str::upper(Str::random(5));
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }
        $this->two_factor_recovery_codes = $hashed;
        $this->two_factor_recovery_generated_at = now();
        $this->save();
        return $plain;
    }

    public function hasRecoveryCodes(): bool
    {
        $codes = $this->two_factor_recovery_codes;
        return is_array($codes) && count($codes) > 0;
    }

    /**
     * 与えられた平文コードが残っていれば消費して true。
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes;
        if (!is_array($codes) || empty($codes)) {
            return false;
        }
        $normalized = trim(Str::upper($code));
        foreach ($codes as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($codes[$index]);
                $this->two_factor_recovery_codes = array_values($codes);
                $this->save();
                return true;
            }
        }
        return false;
    }
}
