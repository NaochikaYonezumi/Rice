<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingEmail extends Model
{
    protected $fillable = [
        'in_reply_to_email_id', 'reply_type', 'to_address', 'cc', 'bcc', 'subject', 'body',
        'attachment_paths', 'status', 'approved_at', 'created_by', 'memo',
    ];

    protected $casts = [
        'approved_at'      => 'datetime',
        'attachment_paths' => 'array',
    ];

    const TYPE_COMPOSE   = 'compose';
    const TYPE_REPLY     = 'reply';
    const TYPE_REPLY_ALL = 'reply_all';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function inReplyToEmail(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'in_reply_to_email_id');
    }

    public function getReplyTypeLabelAttribute(): string
    {
        return match ($this->reply_type) {
            self::TYPE_REPLY     => '返信',
            self::TYPE_REPLY_ALL => '全員に返信',
            default              => '新規作成',
        };
    }

    public function getBodyPreviewAttribute(): string
    {
        return \Illuminate\Support\Str::limit($this->body, 80);
    }
}
