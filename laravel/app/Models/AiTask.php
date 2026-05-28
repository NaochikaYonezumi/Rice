<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTask extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_ERROR      = 'error';

    public const TYPE_THREAD_SUMMARY = 'thread_summary';
    public const TYPE_REPLY_ASSIST   = 'reply_assist';
    public const TYPE_COMPOSE_ASSIST = 'compose_assist';

    protected $fillable = [
        'user_id', 'thread_id', 'task_type', 'status',
        'provider', 'model',
        'prompt', 'result_answer', 'result_meta',
        'error_code', 'error_message',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'result_meta'  => 'array',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];
}
