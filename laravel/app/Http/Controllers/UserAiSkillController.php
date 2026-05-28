<?php

namespace App\Http\Controllers;

use App\Models\UserAiSkill;
use App\Services\AiSkillService;
use Illuminate\Http\Request;

class UserAiSkillController extends Controller
{
    public function __construct(private AiSkillService $service) {}

    public function index()
    {
        $user = auth()->user();
        // 一覧表示前に初回シード
        $this->service->getSkillsForUser($user);
        $skills = UserAiSkill::where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn($s) => $this->present($s))
            ->values();
        return view('settings.ai_skills', ['skills' => $skills]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:128',
            'description'        => 'nullable|string|max:500',
            'system_prompt'      => 'required|string|max:20000',
            'is_default_summary' => 'nullable|boolean',
            'is_default_reply'   => 'nullable|boolean',
            'show_in_summary'    => 'nullable|boolean',
            'show_in_reply'      => 'nullable|boolean',
        ]);
        $user = auth()->user();
        $maxOrder = UserAiSkill::where('user_id', $user->id)->max('sort_order') ?? 0;
        // 表示先が両方 false なら使い物にならないので、片方も指定なければデフォルトで両方 true
        $showSummary = array_key_exists('show_in_summary', $data) ? (bool) $data['show_in_summary'] : true;
        $showReply   = array_key_exists('show_in_reply', $data)   ? (bool) $data['show_in_reply']   : true;
        $skill = UserAiSkill::create([
            'user_id'            => $user->id,
            'skill_key'          => $this->service->generateSkillKey($user, $data['name']),
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'system_prompt'      => $data['system_prompt'],
            'sort_order'         => $maxOrder + 1,
            'is_active'          => true,
            'is_default_summary' => (bool) ($data['is_default_summary'] ?? false),
            'is_default_reply'   => (bool) ($data['is_default_reply'] ?? false),
            'show_in_summary'    => $showSummary,
            'show_in_reply'      => $showReply,
        ]);
        $this->enforceSingleDefault($skill);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'skill' => $this->present($skill->fresh())]);
        }
        return back()->with('success', 'スキルを追加しました。');
    }

    public function update(Request $request, UserAiSkill $skill)
    {
        $this->authorizeOwn($skill);
        $data = $request->validate([
            'name'               => 'required|string|max:128',
            'description'        => 'nullable|string|max:500',
            'system_prompt'      => 'required|string|max:20000',
            'is_active'          => 'nullable|boolean',
            'is_default_summary' => 'nullable|boolean',
            'is_default_reply'   => 'nullable|boolean',
            'show_in_summary'    => 'nullable|boolean',
            'show_in_reply'      => 'nullable|boolean',
        ]);
        $skill->update([
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'system_prompt'      => $data['system_prompt'],
            'is_active'          => (bool) ($data['is_active'] ?? $skill->is_active),
            'is_default_summary' => (bool) ($data['is_default_summary'] ?? false),
            'is_default_reply'   => (bool) ($data['is_default_reply'] ?? false),
            'show_in_summary'    => array_key_exists('show_in_summary', $data) ? (bool) $data['show_in_summary'] : $skill->show_in_summary,
            'show_in_reply'      => array_key_exists('show_in_reply', $data)   ? (bool) $data['show_in_reply']   : $skill->show_in_reply,
        ]);
        $this->enforceSingleDefault($skill);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'skill' => $this->present($skill->fresh())]);
        }
        return back()->with('success', "「{$skill->name}」を更新しました。");
    }

    /**
     * 当該ユーザー内で is_default_summary / is_default_reply が一意になるように、他の行のフラグを解除する。
     */
    private function enforceSingleDefault(UserAiSkill $skill): void
    {
        if ($skill->is_default_summary) {
            UserAiSkill::where('user_id', $skill->user_id)
                ->where('id', '!=', $skill->id)
                ->where('is_default_summary', true)
                ->update(['is_default_summary' => false]);
        }
        if ($skill->is_default_reply) {
            UserAiSkill::where('user_id', $skill->user_id)
                ->where('id', '!=', $skill->id)
                ->where('is_default_reply', true)
                ->update(['is_default_reply' => false]);
        }
    }

    public function destroy(Request $request, UserAiSkill $skill)
    {
        $this->authorizeOwn($skill);
        $name = $skill->name;
        $skill->delete();
        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }
        return back()->with('success', "「{$name}」を削除しました。");
    }

    public function reset(Request $request)
    {
        $this->service->resetToDefaults(auth()->user());
        if ($request->wantsJson()) {
            $skills = UserAiSkill::where('user_id', auth()->id())
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn($s) => $this->present($s));
            return response()->json(['status' => 'ok', 'skills' => $skills]);
        }
        return back()->with('success', 'AI スキルをデフォルトに戻しました。');
    }

    private function present(UserAiSkill $s): array
    {
        $defaultKeys = array_keys(config('ai_skills.skills', []));
        return [
            'id'                 => $s->id,
            'skill_key'          => $s->skill_key,
            'name'               => $s->name,
            'description'        => $s->description,
            'system_prompt'      => $s->system_prompt,
            'is_active'          => (bool) $s->is_active,
            'is_default'         => in_array($s->skill_key, $defaultKeys, true),
            'is_default_summary' => (bool) $s->is_default_summary,
            'is_default_reply'   => (bool) $s->is_default_reply,
            'show_in_summary'    => (bool) $s->show_in_summary,
            'show_in_reply'      => (bool) $s->show_in_reply,
            'updated_at'         => $s->updated_at?->format('Y/m/d H:i'),
        ];
    }

    private function authorizeOwn(UserAiSkill $skill): void
    {
        if ($skill->user_id !== auth()->id()) {
            abort(403, 'このスキルを編集する権限がありません。');
        }
    }
}
