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
        ];
    }
}
