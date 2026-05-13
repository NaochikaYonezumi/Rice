<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 6-3: AI 返信生成ログ (採用率/編集距離トラッキング用)
 *
 * was_adopted:
 *   null  → 未確定 (まだ送信されていない)
 *   1     → 採用 (送信本文と生成案がほぼ同一)
 *   0     → 破棄 (大幅編集 or 別文)
 */
class AiLog extends Model
{
    protected $table = 'ext_ai_logs';

    protected $fillable = [
        'email_thread_id',
        'pending_email_id',
        'user_id',
        'provider',
        'collection',
        'prompt_summary',
        'generated_reply',
        'confidence_score',
        'safety_checks',
        'was_adopted',
        'edit_distance',
        'sent_at',
    ];

    protected $casts = [
        'safety_checks' => 'array',
        'confidence_score' => 'integer',
        'was_adopted' => 'integer',
        'edit_distance' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'email_thread_id');
    }

    public function pendingEmail(): BelongsTo
    {
        return $this->belongsTo(PendingEmail::class, 'pending_email_id');
    }

    public function scopeAdopted($query)
    {
        return $query->where('was_adopted', 1);
    }

    public function scopeDiscarded($query)
    {
        return $query->where('was_adopted', 0);
    }
}
