<?php

namespace Modules\Workflow\Services;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Workflow\Models\WorkflowLog;
use Modules\Workflow\Models\WorkflowRule;

/**
 * メールスレッドのワークフロー (タグ付け・自動割当) を実行するエンジン。
 *
 * Phase 6-2 で以下を追加:
 *  - assignByRule(EmailThread)        : 新ルール (match_type/match_value/assign_user_id) で割当先を解決
 *  - assignByRoundRobin(EmailThread)  : 担当者をラウンドロビンで決定 (1 周したら先頭に戻る)
 *  - autoAssign(EmailThread)          : assignByRule → 失敗時 RR の順で割当、履歴を保存
 *
 * 既存の process(EmailThread, Email) は破壊しない (タグ付けロジック等を継続提供)
 */
class WorkflowEngine
{
    /**
     * 旧来の処理: タグ付け + 担当者割当 (条件式ルール + RR フォールバック)。
     * 既存呼び出しを破壊しないために残置。
     */
    public function process(EmailThread $thread, Email $email)
    {
        $rules = DB::table('ext_workflow_rules')
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($this->checkLegacyCondition($rule, $email)) {
                $this->applyLegacyActions($rule, $thread);
            }
        }

        // 担当者がまだ決まっていない場合のラウンドロビン (指示書要件)
        if (!$thread->assigned_user_id) {
            $this->applyRoundRobinLegacy($thread);
        }
    }

    /**
     * Phase 6-2: 新ルール (match_type / match_value / assign_user_id) を評価し、
     * マッチした最初のルールの assignee を返す。マッチしなければ null。
     */
    public function assignByRule(EmailThread $thread): ?User
    {
        $email = $thread->emails()->orderByDesc('received_at')->first();
        if (!$email) return null;

        $rules = WorkflowRule::query()
            ->active()
            ->byPriority()
            ->whereNotNull('match_type')
            ->whereNotNull('match_value')
            ->whereNotNull('assign_user_id')
            ->get();

        foreach ($rules as $rule) {
            if ($this->matches($rule, $email)) {
                return User::find($rule->assign_user_id);
            }
        }
        return null;
    }

    /**
     * Phase 6-2: ラウンドロビンで次の担当者を返す。一周したら先頭に戻る。
     */
    public function assignByRoundRobin(EmailThread $thread): ?User
    {
        // 管理者以外のメンバーを対象 (既存実装と同じ)
        $users = User::where('role', 'member')->orderBy('id')->get();
        if ($users->isEmpty()) return null;

        $last = DB::table('ext_workflow_round_robin')
            ->where('group_key', 'default')
            ->first();
        $lastId = $last?->last_assigned_user_id;

        // 「直前のユーザの次のユーザ」を探す。なければ先頭にループ。
        $next = $users->first(fn($u) => $u->id > $lastId) ?? $users->first();

        DB::table('ext_workflow_round_robin')->updateOrInsert(
            ['group_key' => 'default'],
            ['last_assigned_user_id' => $next->id, 'updated_at' => now(), 'created_at' => $last?->created_at ?? now()]
        );

        return $next;
    }

    /**
     * Phase 6-2: スレッドに対する自動割当のメインエントリ。
     *  1) ルールで割当先が決まればそれを使う
     *  2) ダメならラウンドロビンで割当
     *  3) thread.assigned_user_id を更新し、ext_workflow_logs に履歴を記録
     */
    public function autoAssign(EmailThread $thread): void
    {
        $rule = null;
        $user = null;
        $assignedBy = WorkflowLog::ASSIGNED_BY_RULE;

        // 1) ルール評価
        $email = $thread->emails()->orderByDesc('received_at')->first();
        if ($email) {
            $candidates = WorkflowRule::active()->byPriority()
                ->whereNotNull('match_type')
                ->whereNotNull('match_value')
                ->whereNotNull('assign_user_id')
                ->get();
            foreach ($candidates as $r) {
                if ($this->matches($r, $email)) {
                    $rule = $r;
                    $user = User::find($r->assign_user_id);
                    break;
                }
            }
        }

        // 2) ルール無し → RR
        if (!$user) {
            $user = $this->assignByRoundRobin($thread);
            $assignedBy = WorkflowLog::ASSIGNED_BY_ROUND_ROBIN;
        }

        if (!$user) return; // 割当先候補が一切いない場合は何もしない

        $thread->update(['assigned_user_id' => $user->id]);

        WorkflowLog::create([
            'thread_id'        => $thread->id,
            'assigned_user_id' => $user->id,
            'rule_id'          => $rule?->id,
            'assigned_by'      => $assignedBy,
            'created_at'       => now(),
        ]);
    }

    // ===================== ルールマッチング =====================
    /**
     * 新ルールが指定メールにマッチするか判定。
     */
    public function matches(WorkflowRule $rule, Email $email): bool
    {
        $value = (string) $rule->match_value;
        if ($value === '') return false;

        return match ($rule->match_type) {
            WorkflowRule::MATCH_FROM_ADDRESS     => (string) $email->from_address === $value,
            WorkflowRule::MATCH_FROM_DOMAIN      => $this->matchDomain((string) $email->from_address, $value),
            WorkflowRule::MATCH_SUBJECT_CONTAINS => $value !== '' && str_contains((string) $email->subject, $value),
            WorkflowRule::MATCH_TO_ADDRESS       => $this->matchToAddress((string) $email->to_address, $value),
            default => false,
        };
    }

    private function matchDomain(string $address, string $expectedDomain): bool
    {
        $at = strrpos($address, '@');
        if ($at === false) return false;
        $domain = substr($address, $at + 1);
        return strtolower(trim($domain)) === strtolower(trim($expectedDomain));
    }

    private function matchToAddress(string $toAddresses, string $expected): bool
    {
        $list = array_filter(array_map('trim', explode(',', $toAddresses)));
        return in_array($expected, $list, true);
    }

    // ===================== Legacy =====================
    protected function checkLegacyCondition($rule, $email): bool
    {
        $valueToTest = ($rule->condition_type === 'subject') ? $email->subject : $email->from_address;

        return match ($rule->condition_operator) {
            'contains' => is_string($valueToTest) && str_contains($valueToTest, $rule->condition_value),
            'equals'   => $valueToTest === $rule->condition_value,
            'regex'    => @preg_match($rule->condition_value, (string) $valueToTest) === 1,
            default    => false,
        };
    }

    protected function applyLegacyActions($rule, $thread): void
    {
        $actions = is_array($rule->actions) ? $rule->actions : json_decode((string) $rule->actions, true);
        if (!is_array($actions)) return;

        if (!empty($actions['tags_to_add'])) {
            $currentTags = is_array($thread->tags) ? $thread->tags : [];
            $newTags = array_values(array_unique(array_merge($currentTags, $actions['tags_to_add'])));
            $thread->update(['tags' => $newTags]);
        }

        if (!empty($actions['assign_user_id'])) {
            $thread->update(['assigned_user_id' => $actions['assign_user_id']]);
        }
    }

    protected function applyRoundRobinLegacy($thread): void
    {
        $user = $this->assignByRoundRobin($thread);
        if ($user) {
            $thread->update(['assigned_user_id' => $user->id]);
        }
    }
}
