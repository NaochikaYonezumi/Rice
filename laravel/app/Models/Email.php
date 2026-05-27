<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    protected $fillable = [
        'thread_id', 'message_id', 'in_reply_to', 'subject',
        'from_address', 'from_name', 'to_address', 'cc', 'bcc',
        'body_text', 'body_html', 'is_read', 'received_at',
        'owner_user_id', 'mail_account_id',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    protected $appends = ['from_label', 'plain_body'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    /**
     * 個人所有 (owner_user_id != null) のメールは所有者しか閲覧できないようにする。
     * システム共有 (owner_user_id == null) は誰でも閲覧可能。
     */
    public function scopeVisibleTo($query, ?int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('owner_user_id');
            if ($userId !== null) {
                $q->orWhere('owner_user_id', $userId);
            }
        });
    }

    public function getFromLabelAttribute(): string
    {
        return $this->from_name ?: $this->from_address;
    }

    public function getPlainBodyAttribute(): string
    {
        if ($this->body_text) {
            return $this->body_text;
        }
        return strip_tags($this->body_html ?? '');
    }
}
