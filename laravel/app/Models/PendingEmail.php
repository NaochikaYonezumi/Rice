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

    /**
     * 指定スレッドに紐づく PendingEmail (status=pending) の有無に応じて
     * EmailThread.status を inbox <-> pending 間で同期する。
     *
     * - 承認待ちが存在し、現在 inbox なら → pending に
     * - 承認待ちが 1 件もなく、現在 pending なら → inbox に戻す
     * - 既に hold / completed / no_action 等にユーザーが移していれば触らない
     */
    public static function syncThreadStatus(?int $threadId): void
    {
        if (!$threadId) return;
        /** @var EmailThread|null $thread */
        $thread = EmailThread::find($threadId);
        if (!$thread) return;

        $hasPending = self::whereHas('inReplyToEmail', fn($q) => $q->where('thread_id', $threadId))
            ->where('status', self::STATUS_PENDING)
            ->exists();

        if ($hasPending && $thread->status === EmailThread::STATUS_INBOX) {
            $thread->update(['status' => EmailThread::STATUS_AWAITING_APPROVAL]);
        } elseif (!$hasPending && $thread->status === EmailThread::STATUS_AWAITING_APPROVAL) {
            $thread->update(['status' => EmailThread::STATUS_INBOX]);
        }
    }
}
