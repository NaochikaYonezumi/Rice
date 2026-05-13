<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingEmail extends Model
{
    protected $fillable = [
        'in_reply_to_email_id', 'reply_type', 'from_address', 'to_address', 'cc', 'bcc', 'subject', 'body',
        'attachment_paths', 'status', 'approved_at', 'created_by', 'memo',
        'created_by_user_id', 'approved_by_user_id', 'rejected_by_user_id',
        'target_approver_user_id',
        'rejection_reason', 'rejected_at',
        'source_rejected_id',
        // Phase 6-3: 紐づく AI 生成ログ
        'ai_log_id',
    ];

    protected $casts = [
        'approved_at'      => 'datetime',
        'rejected_at'      => 'datetime',
        'attachment_paths' => 'array',
    ];

    const TYPE_COMPOSE   = 'compose';
    const TYPE_REPLY     = 'reply';
    const TYPE_REPLY_ALL = 'reply_all';

    const STATUS_DRAFT    = 'draft';
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function inReplyToEmail(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'in_reply_to_email_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function targetApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_approver_user_id');
    }

    /** Phase 6-3: 採用率算定で参照する元 AI ログ */
    public function aiLog(): BelongsTo
    {
        return $this->belongsTo(AiLog::class, 'ai_log_id');
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
