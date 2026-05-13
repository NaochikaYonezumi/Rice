<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'email',
        'password',
        'role',
        'email_verified_at',
        // Phase 6-4: Agent 別メール署名
        'signature_text',
        'signature_html',
        'signature_enabled',
    ];

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
            'signature_enabled' => 'boolean',
        ];
    }

    /**
     * Phase 6-4: メール送信時に末尾に付与する署名を返す。
     *
     * 解決順:
     *   1. signature_enabled が false → null (署名なし)
     *   2. signature_html があれば signature_html
     *   3. signature_text があれば signature_text
     *   4. どちらも空なら AiSetting.agent_signature (グローバル)
     *
     * 戻り値:
     *   - 'html' / 'text' のいずれかの種別と本文
     *   - 完全に署名なしの場合は ['type' => null, 'content' => null]
     *
     * @return array{type:?string, content:?string}
     */
    public function effectiveSignature(): array
    {
        if (!$this->signature_enabled) {
            return ['type' => null, 'content' => null];
        }
        if (!empty($this->signature_html)) {
            return ['type' => 'html', 'content' => (string) $this->signature_html];
        }
        if (!empty($this->signature_text)) {
            return ['type' => 'text', 'content' => (string) $this->signature_text];
        }
        try {
            $global = \App\Models\AiSetting::getSettings()?->agent_signature;
            if (!empty($global)) {
                return ['type' => 'text', 'content' => (string) $global];
            }
        } catch (\Throwable $e) { /* noop */ }
        return ['type' => null, 'content' => null];
    }

    /** 旧 EmailSender 互換用 (テキスト署名のみ返す) */
    public function getSignatureAttribute(): ?string
    {
        $sig = $this->effectiveSignature();
        return $sig['type'] !== null ? $sig['content'] : null;
    }
}
