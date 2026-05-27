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
    public const STATUS_AWAITING_APPROVAL = 'pending';

    protected $fillable = ['subject', 'last_email_at', 'tags', 'customer_id', 'status', 'is_pinned', 'assigned_user_id', 'owner_user_id', 'mail_account_id'];

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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    /**
     * 個人所有スレッドは所有者にのみ閲覧可。owner_user_id IS NULL のスレッドは全員可。
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
}
