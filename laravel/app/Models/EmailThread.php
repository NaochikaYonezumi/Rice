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

    protected $fillable = ['subject', 'ticket_number', 'last_email_at', 'tags', 'customer_id', 'status', 'is_pinned', 'assigned_user_id'];

    protected $casts = [
        'last_email_at' => 'datetime',
        'tags' => 'array',
        'status' => 'string',
        'is_pinned' => 'boolean',
    ];

    /**
     * チケット番号のフォーマット (例: RICE-000123)
     */
    public const TICKET_PREFIX = 'RICE-';
    public const TICKET_PAD    = 6;
    public const TICKET_REGEX  = '/\[#?(RICE-\d{1,12})\]/i';

    public static function generateTicketNumber(int $threadId): string
    {
        return self::TICKET_PREFIX . str_pad((string) $threadId, self::TICKET_PAD, '0', STR_PAD_LEFT);
    }

    /**
     * 件名からチケット番号を抽出 (見つからなければ null)
     */
    public static function extractTicketNumber(?string $subject): ?string
    {
        if (!$subject) return null;
        if (preg_match(self::TICKET_REGEX, $subject, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    /**
     * 件名にチケット番号タグを付与 (既に含まれていればそのまま)
     */
    public static function ensureTicketInSubject(string $subject, string $ticketNumber): string
    {
        if (preg_match(self::TICKET_REGEX, $subject)) {
            return $subject;
        }
        return '[#' . $ticketNumber . '] ' . trim($subject);
    }

    public function ensureTicketNumber(): string
    {
        // マイグレーション未実行環境でも落ちないようにガード
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('email_threads', 'ticket_number')) {
                return self::generateTicketNumber($this->id);
            }
        } catch (\Throwable $e) {
            return self::generateTicketNumber($this->id);
        }

        if (!$this->ticket_number) {
            try {
                $this->ticket_number = self::generateTicketNumber($this->id);
                $this->save();
            } catch (\Throwable $e) {
                // 書き込み失敗時もアプリは継続
                return self::generateTicketNumber($this->id);
            }
        }
        return $this->ticket_number;
    }

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
