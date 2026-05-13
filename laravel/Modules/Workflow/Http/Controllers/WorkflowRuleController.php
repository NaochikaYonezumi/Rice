<?php

namespace Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Models\WorkflowRule;

/**
 * Phase 6-2: ワークフロー自動割当ルール管理 (admin)
 *
 * - GET    /admin/workflow-rules        index ページ (HTML)
 * - GET    /admin/workflow-rules/list   一覧 (JSON, Alpine fetch 用)
 * - POST   /admin/workflow-rules        新規作成
 * - PUT    /admin/workflow-rules/{rule} 更新
 * - DELETE /admin/workflow-rules/{rule} 削除
 * - POST   /admin/workflow-rules/{rule}/toggle 有効/無効切替
 */
class WorkflowRuleController extends Controller
{
    public function index()
    {
        // 担当者候補 (admin 以外を取得)
        $users = User::where('role', 'member')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        return view('workflow::rules.index', ['users' => $users]);
    }

    public function list(): JsonResponse
    {
        $rules = WorkflowRule::with('assignee')
            ->byPriority()
            ->get()
            ->map(fn (WorkflowRule $r) => $this->present($r));
        return response()->json(['rules' => $rules]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRule($request);
        $rule = WorkflowRule::create($validated + ['is_active' => true]);
        $rule->load('assignee');
        return response()->json(['rule' => $this->present($rule)], 201);
    }

    public function update(Request $request, WorkflowRule $rule): JsonResponse
    {
        $validated = $this->validateRule($request);
        $rule->update($validated);
        $rule->load('assignee');
        return response()->json(['rule' => $this->present($rule)]);
    }

    public function destroy(WorkflowRule $rule): JsonResponse
    {
        $rule->delete();
        return response()->json(['status' => 'ok']);
    }

    public function toggle(WorkflowRule $rule): JsonResponse
    {
        $rule->update(['is_active' => !$rule->is_active]);
        $rule->load('assignee');
        return response()->json(['rule' => $this->present($rule)]);
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name'           => 'required|string|max:120',
            'match_type'     => 'required|string|in:' . implode(',', WorkflowRule::MATCH_TYPES),
            'match_value'    => 'required|string|max:255',
            'assign_user_id' => 'required|integer|exists:users,id',
            'priority'       => 'nullable|integer|min:0|max:1000',
            'is_active'      => 'nullable|boolean',
        ]);
    }

    private function present(WorkflowRule $rule): array
    {
        return [
            'id'             => $rule->id,
            'name'           => $rule->name,
            'match_type'     => $rule->match_type,
            'match_value'    => $rule->match_value,
            'priority'       => (int) $rule->priority,
            'is_active'      => (bool) $rule->is_active,
            'assign_user_id' => $rule->assign_user_id,
            'assignee_name'  => $rule->assignee?->name,
            'updated_at'     => $rule->updated_at?->format('Y/m/d H:i'),
        ];
    }
}
