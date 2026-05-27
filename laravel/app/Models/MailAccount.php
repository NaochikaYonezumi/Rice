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
        ];
    }

    protected $hidden = [
        'imap_password',
        'pop_password',
        'smtp_password',
    ];

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
        return $this->is_active
            && in_array($this->inbox_protocol, [self::PROTOCOL_IMAP, self::PROTOCOL_POP3], true)
            && !empty($this->effectiveInboxHost())
            && !empty($this->effectiveInboxUsername());
    }

    public function canSend(): bool
    {
        return $this->is_active
            && $this->smtp_enabled
            && !empty($this->smtp_host)
            && !empty($this->smtp_username);
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
