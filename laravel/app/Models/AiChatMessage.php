<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI チャット 1 メッセージ. user / assistant のターン.
 *
 * assistant メッセージは生成中は status='pending', 完了で 'done', 失敗で 'error'.
 * user メッセージは常に 'done'.
 */
class AiChatMessage extends Model
{
    use HasFactory;

    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE    = 'done';
    public const STATUS_ERROR   = 'error';

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'status',
        'error_code',
        'error_message',
        'elapsed_ms',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }
}
