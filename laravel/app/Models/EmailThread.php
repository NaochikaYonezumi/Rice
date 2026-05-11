<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailThread extends Model
{
    public const STATUS_INBOX = 'inbox';
    public const STATUS_HOLD = 'hold';
    public const STATUS_DONE = 'completed';
    public const STATUS_NO_ACTION = 'no_action';
    public const STATUS_AWAITING_APPROVAL = 'pending';

    /** UI 表示・集計で使用するステータスの順序付き一覧 */
    public const STATUSES = [
        self::STATUS_INBOX,
        self::STATUS_HOLD,
        self::STATUS_DONE,
        self::STATUS_NO_ACTION,
        self::STATUS_AWAITING_APPROVAL,
    ];

    protected $fillable = ['subject', 'last_email_at', 'tags', 'customer_id', 'status', 'is_pinned', 'assigned_user_id'];

    protected $casts = [
        'last_email_at' => 'datetime',
        'tags' => 'array',
        'status' => 'string',
        'is_pinned' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class, 'thread_id')->orderBy('received_at');
    }

    public function latestEmail()
    {
        return $this->hasOne(Email::class, 'thread_id')->latestOfMany('received_at');
    }

    public function threadMerges(): HasMany
    {
        return $this->hasMany(ThreadMerge::class, 'target_thread_id')->orderByDesc('created_at');
    }

    public function threadMemos(): HasMany
    {
        return $this->hasMany(ThreadMemo::class, 'thread_id')->orderByDesc('created_at');
    }

    public function threadComments(): HasMany
    {
        return $this->hasMany(ThreadComment::class, 'thread_id')->orderBy('created_at');
    }
}
