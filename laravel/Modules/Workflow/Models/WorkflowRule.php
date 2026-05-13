<?php

namespace Modules\Workflow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 6-2: 送信元別 自動割当ルール
 *
 * match_type:
 *   - 'from_address'      : 完全一致
 *   - 'from_domain'       : ドメイン (@xxx の右辺) 完全一致
 *   - 'subject_contains'  : 件名部分一致
 *   - 'to_address'        : To アドレス完全一致 (一致リストの中に含まれるか)
 */
class WorkflowRule extends Model
{
    protected $table = 'ext_workflow_rules';

    public const MATCH_FROM_ADDRESS     = 'from_address';
    public const MATCH_FROM_DOMAIN      = 'from_domain';
    public const MATCH_SUBJECT_CONTAINS = 'subject_contains';
    public const MATCH_TO_ADDRESS       = 'to_address';

    public const MATCH_TYPES = [
        self::MATCH_FROM_ADDRESS,
        self::MATCH_FROM_DOMAIN,
        self::MATCH_SUBJECT_CONTAINS,
        self::MATCH_TO_ADDRESS,
    ];

    protected $fillable = [
        'name',
        'match_type',
        'match_value',
        'assign_user_id',
        'priority',
        'is_active',
        // 既存カラム (互換)
        'condition_type', 'condition_operator', 'condition_value', 'actions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority'  => 'integer',
        'actions'   => 'array',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_user_id');
    }

    /** 有効なルールのみ */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** 優先度順 (priority が小さい方が先) */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('id', 'asc');
    }
}
