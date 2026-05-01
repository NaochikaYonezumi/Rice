<?php

namespace Modules\Workflow\Services;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    /**
     * Process an incoming email thread through workflow rules.
     */
    public function process(EmailThread $thread, Email $email)
    {
        $rules = DB::table('ext_workflow_rules')
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($this->checkCondition($rule, $email)) {
                $this->applyActions($rule, $thread);
            }
        }

        // 担当者がまだ決まっていない場合のラウンドロビン (指示書要件)
        if (!$thread->assigned_user_id) {
            $this->applyRoundRobin($thread);
        }
    }

    protected function checkCondition($rule, $email)
    {
        $valueToTest = ($rule->condition_type === 'subject') ? $email->subject : $email->from_address;
        
        return match ($rule->condition_operator) {
            'contains' => str_contains($valueToTest, $rule->condition_value),
            'equals'   => $valueToTest === $rule->condition_value,
            'regex'    => preg_match($rule->condition_value, $valueToTest),
            default    => false,
        };
    }

    protected function applyActions($rule, $thread)
    {
        $actions = json_decode($rule->actions, true);

        // タグの追加 (EmailThreadのタグ保存ロジックに依存)
        if (!empty($actions['tags_to_add'])) {
            $currentTags = is_array($thread->tags) ? $thread->tags : [];
            $newTags = array_unique(array_merge($currentTags, $actions['tags_to_add']));
            $thread->update(['tags' => $newTags]);
        }

        // 担当者の割り当て
        if (!empty($actions['assign_user_id'])) {
            $thread->update(['assigned_user_id' => $actions['assign_user_id']]);
        }
    }

    protected function applyRoundRobin($thread)
    {
        // 管理者以外のメンバーを対象にする想定
        $users = User::where('role', 'member')->orderBy('id')->get();
        if ($users->isEmpty()) return;

        $lastRecord = DB::table('ext_workflow_round_robin')->where('group_key', 'default')->first();
        $lastUserId = $lastRecord?->last_assigned_user_id;

        $nextUser = $users->first(fn($u) => $u->id > $lastUserId) ?? $users->first();

        $thread->update(['assigned_user_id' => $nextUser->id]);

        DB::table('ext_workflow_round_robin')->updateOrInsert(
            ['group_key' => 'default'],
            ['last_assigned_user_id' => $nextUser->id, 'updated_at' => now()]
        );
    }
}
