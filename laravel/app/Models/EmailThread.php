<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailThread extends Model
{
    public const STATUS_INBOX = 'inbox';
    public const STATUS_HOLD = 'hold';
    public const STATUS_DONE = 'completed';
    public const STATUS_NO_ACTION = 'no_action';
    public const STATUS_AWAITING_APPROVAL = 'pending';
    public const STATUS_SPAM = 'spam';   // 迷惑メール (デフォルト一覧から非表示。専用タブで表示)
    // ゴミ箱. 削除ボタンを押した直後の状態. trashed_at と組で立てる.
    // mail:purge-trash が trash_retention_days 経過後に cascade ハード削除する.
    public const STATUS_TRASH = 'trash';

    /**
     * ゴミ箱 / 迷惑メールの保持期間 (日) のフォールバック既定値.
     *
     * 実際の値は MailSetting::trashRetentionDays() / spamRetentionDays() から取得し、
     * 管理者が「設定 → メール → 保持期間」で変更可能. ここの定数は mail_settings 行が
     * 無い / カラム未存在の場合のフォールバック (= マイグレーション未適用環境やテスト用).
     *
     * 旧コードからの呼び出し互換のため、定数は引き続き残す.
     */
    public const TRASH_RETENTION_DAYS = 30;
    public const SPAM_RETENTION_DAYS  = 30;

    protected $fillable = ['subject', 'ticket_number', 'last_email_at', 'tags', 'customer_id', 'status', 'is_pinned', 'assigned_user_id', 'is_manual_upload', 'trashed_at', 'spammed_at'];

    protected $casts = [
        'last_email_at' => 'datetime',
        'trashed_at'    => 'datetime',
        'spammed_at'    => 'datetime',
        'tags' => 'array',
        'status' => 'string',
        'is_pinned' => 'boolean',
        'is_manual_upload' => 'boolean',
    ];

    /**
     * このスレッドは現在ゴミ箱に入っているか.
     * status='trash' と trashed_at の AND を取って判定する.
     */
    public function isTrashed(): bool
    {
        return $this->status === self::STATUS_TRASH && $this->trashed_at !== null;
    }

    /**
     * このスレッドは現在迷惑メール扱いか. status='spam' で判定 (spammed_at は purge 起点専用).
     */
    public function isSpam(): bool
    {
        return $this->status === self::STATUS_SPAM;
    }

    /**
     * 保持期間取得ヘルパ (動的). MailSetting に値があればそれを、無ければ定数を返す.
     * mail:purge-trash / mail:purge-spam / API レスポンス / UI 残日数表示 から呼ぶ.
     */
    public static function trashRetentionDays(): int
    {
        return MailSetting::trashRetentionDays();
    }
    public static function spamRetentionDays(): int
    {
        return MailSetting::spamRetentionDays();
    }

    /**
     * チケット番号のフォーマット (例: TICKET-000123)
     *
     * 過去の運用で RICE- プレフィックスを使っていた期間があるため、
     * 受信メール件名から既存スレッドを引き当てる時は両方の prefix を許可する。
     * 新規生成は TICKET_PREFIX (= TICKET-) のみ。
     */
    public const TICKET_PREFIX = 'TICKET-';
    public const TICKET_PAD    = 6;
    public const TICKET_REGEX  = '/\[#?((?:TICKET|RICE)-\d{1,12})\]/i';

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

    /**
     * 紐付けられている共有/個人ルーム (chat_room_thread ピボット経由).
     * ChatRoom::bundledThreads の逆向き。 マージ時のバンドル移送等で使う。
     */
    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_thread', 'email_thread_id', 'chat_room_id')->withTimestamps();
    }
}
