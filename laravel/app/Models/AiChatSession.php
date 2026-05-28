<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI チャットセッション (ユーザ × スレッド × kind で 1 件).
 *
 * kind:
 *   - summary : スレッド要約をブラッシュアップする会話
 *   - reply   : 返信案をブラッシュアップする会話
 */
class AiChatSession extends Model
{
    use HasFactory;

    public const KIND_SUMMARY = 'summary';
    public const KIND_REPLY   = 'reply';

    protected $fillable = [
        'user_id',
        'thread_id',
        'kind',
        'provider',
        'model',
        'system_prompt',
        'skill_key',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'thread_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'session_id')->orderBy('created_at');
    }
}
