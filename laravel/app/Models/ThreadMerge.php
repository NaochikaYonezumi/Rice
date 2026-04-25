<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadMerge extends Model
{
    protected $fillable = [
        'target_thread_id', 'source_thread_id_original',
        'source_subject', 'source_tags', 'merged_email_ids',
    ];

    protected $casts = [
        'source_tags'      => 'array',
        'merged_email_ids' => 'array',
    ];

    public function targetThread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'target_thread_id');
    }
}
