<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingEmail extends Model
{
    protected $fillable = [
        'in_reply_to_email_id', 'reply_type', 'mail_account_id', 'from_address', 'to_address', 'cc', 'bcc', 'subject',
        // body は互換用のプレーンテキスト本文。 body_html は HTML 本文 (リッチエディタの出力)。
        // 送信時は両方を multipart に乗せる。
        'body', 'body_html',
        'attachment_paths', 'status', 'approved_at', 'created_by', 'memo',
        'created_by_user_id', 'approved_by_user_id', 'rejected_by_user_id',
        'target_approver_user_id',
        'rejection_reason', 'rejected_at',
        'source_rejected_id',
        // 予約送信用 (タスク #112)
        'scheduled_for', 'send_attempts', 'last_send_error',
    ];

    protected $casts = [
        'approved_at'      => 'datetime',
        'rejected_at'      => 'datetime',
        'scheduled_for'    => 'datetime',
        'attachment_paths' => 'array',
    ];

    const TYPE_COMPOSE   = 'compose';
    const TYPE_REPLY     = 'reply';
    const TYPE_REPLY_ALL = 'reply_all';
    /** 転送 (Fwd:). reply と違って件名に "Fwd: " を付け、本文に元メールを引用する. */
    const TYPE_FORWARD   = 'forward';

    const STATUS_DRAFT     = 'draft';
    const STATUS_PENDING   = 'pending';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    // ★ 予約送信: scheduled_for で指定された日時に送信される.
    //   コマンド mail:send-scheduled が cron 経由でポーリングして status を approved に遷移させる.
    const STATUS_SCHEDULED = 'scheduled';

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

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    public function getReplyTypeLabelAttribute(): string
    {
        return match ($this->reply_type) {
            self::TYPE_REPLY     => '返信',
            self::TYPE_REPLY_ALL => '全員に返信',
            self::TYPE_FORWARD   => '転送',
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
