<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailAccount extends Model
{
    public const PROTOCOL_IMAP = 'imap';
    public const PROTOCOL_POP3 = 'pop3';
    public const PROTOCOL_DISABLED = 'disabled';

    public const AUTH_PASSWORD          = 'password';
    public const AUTH_OAUTH_MICROSOFT   = 'oauth_microsoft';
    // (将来) public const AUTH_OAUTH_GOOGLE = 'oauth_google';

    protected $fillable = [
        'user_id',
        'name',
        'email_address',
        'is_active',
        'inbox_protocol',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'pop_host', 'pop_port', 'pop_encryption', 'pop_username', 'pop_password',
        'smtp_enabled',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'smtp_from_name',
        'last_fetched_at',
        'auth_type', 'oauth_provider',
        'oauth_access_token', 'oauth_refresh_token', 'oauth_expires_at', 'oauth_scope',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'smtp_enabled' => 'boolean',
            'imap_port' => 'integer',
            'pop_port' => 'integer',
            'smtp_port' => 'integer',
            'imap_password' => 'encrypted',
            'pop_password' => 'encrypted',
            'smtp_password' => 'encrypted',
            'last_fetched_at' => 'datetime',
            'oauth_access_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_expires_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'imap_password',
        'pop_password',
        'smtp_password',
        'oauth_access_token',
        'oauth_refresh_token',
    ];

    public function isOAuth(): bool
    {
        return $this->auth_type && $this->auth_type !== self::AUTH_PASSWORD;
    }

    /**
     * OAuth トークンが有効期限内か(60秒余裕を見る). 期限切れなら refresh が必要.
     */
    public function isAccessTokenValid(): bool
    {
        if (!$this->oauth_access_token) return false;
        if (!$this->oauth_expires_at)    return true; // 期限不明なら一旦有効扱い
        return now()->addSeconds(60)->lt($this->oauth_expires_at);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function emailThreads(): HasMany
    {
        return $this->hasMany(EmailThread::class);
    }

    public function canReceive(): bool
    {
        if (!$this->is_active) return false;
        if (!in_array($this->inbox_protocol, [self::PROTOCOL_IMAP, self::PROTOCOL_POP3], true)) return false;
        if (empty($this->effectiveInboxHost())) return false;
        if ($this->isOAuth()) {
            return !empty($this->oauth_access_token) || !empty($this->oauth_refresh_token);
        }
        return !empty($this->effectiveInboxUsername());
    }

    public function canSend(): bool
    {
        if (!$this->is_active || !$this->smtp_enabled) return false;
        if (empty($this->smtp_host)) return false;
        if ($this->isOAuth()) {
            return !empty($this->oauth_access_token) || !empty($this->oauth_refresh_token);
        }
        return !empty($this->smtp_username);
    }

    public function effectiveInboxHost(): ?string
    {
        return $this->inbox_protocol === self::PROTOCOL_POP3 ? $this->pop_host : $this->imap_host;
    }

    public function effectiveInboxUsername(): ?string
    {
        return $this->inbox_protocol === self::PROTOCOL_POP3 ? $this->pop_username : $this->imap_username;
    }
}
