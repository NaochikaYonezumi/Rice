<?php

namespace Modules\Workflow\Models;

use App\Models\EmailThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 6-2: 自動割当履歴
 *  - assigned_by: 'rule' | 'round_robin' | 'manual'
 */
class WorkflowLog extends Model
{
    protected $table = 'ext_workflow_logs';

    public const ASSIGNED_BY_RULE         = 'rule';
    public const ASSIGNED_BY_ROUND_ROBIN  = 'round_robin';
    public const ASSIGNED_BY_MANUAL       = 'manual';

    public $timestamps = false;

    protected $fillable = [
        'thread_id', 'assigned_user_id', 'rule_id', 'assigned_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'thread_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(WorkflowRule::class, 'rule_id');
    }
}
