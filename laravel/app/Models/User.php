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
     * 個別設定された署名 → 全体設定の agent_signature → 簡易フォールバック
     */
    public function resolvedSignature(): string
    {
        if (!empty($this->signature)) return $this->signature;
        try {
            $ai = \App\Models\AiSetting::getSettings();
            if (!empty($ai->agent_signature)) return $ai->agent_signature;
        } catch (\Throwable) {}
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
