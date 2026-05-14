<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAiSkill;
use Illuminate\Support\Str;

class AiSkillService
{
    /**
     * 指定ユーザーのアクティブなスキル一覧を keyed array で返す。
     * ユーザーが 1 件も持っていなければ config のデフォルトをシードしてから返す。
     *
     * 引数 $scope で 'summary' / 'reply' / null (=全部) の絞り込みが可能。
     * 返却形: ['<skill_key>' => ['name' => '...', ...], ...]
     */
    public function getSkillsForUser(User $user, ?string $scope = null): array
    {
        $count = UserAiSkill::where('user_id', $user->id)->count();
        if ($count === 0) {
            $this->seedDefaults($user);
        }

        $q = UserAiSkill::where('user_id', $user->id)->where('is_active', true);
        if ($scope === 'summary') {
            $q->where('show_in_summary', true);
        } elseif ($scope === 'reply') {
            $q->where('show_in_reply', true);
        }

        return $q->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($s) => [
                $s->skill_key => [
                    'name'               => $s->name,
                    'description'        => $s->description,
                    'system_prompt'      => $s->system_prompt,
                    'is_default_summary' => (bool) $s->is_default_summary,
                    'is_default_reply'   => (bool) $s->is_default_reply,
                    'show_in_summary'    => (bool) $s->show_in_summary,
                    'show_in_reply'      => (bool) $s->show_in_reply,
                ],
            ])
            ->toArray();
    }

    /**
     * config の skills をユーザーのテーブルに複製。既存の skill_key は上書きしない。
     * 'summarize' は AI 要約のデフォルト、'reply' はメール返信のデフォルトに自動マーク。
     */
    public function seedDefaults(User $user): void
    {
        $defaults = config('ai_skills.skills', []);
        $order = 0;
        foreach ($defaults as $key => $def) {
            // 初期推測: summarize / action_items は要約用、reply は返信用、両方有効なら両方
            $isSummaryOriented = in_array($key, ['summarize', 'action_items'], true);
            $isReplyOriented   = in_array($key, ['reply'], true);
            UserAiSkill::firstOrCreate(
                ['user_id' => $user->id, 'skill_key' => $key],
                [
                    'name'               => $def['name'] ?? $key,
                    'description'        => $def['description'] ?? null,
                    'system_prompt'      => $def['system_prompt'] ?? '',
                    'sort_order'         => $order++,
                    'is_active'          => true,
                    'is_default_summary' => $key === 'summarize',
                    'is_default_reply'   => $key === 'reply',
                    // 推奨カテゴリ。両方 false にはせず、デフォルトでは推奨側を入れる
                    'show_in_summary'    => $isSummaryOriented || (!$isSummaryOriented && !$isReplyOriented),
                    'show_in_reply'      => $isReplyOriented   || (!$isSummaryOriented && !$isReplyOriented),
                ]
            );
        }
    }

    /**
     * ユーザーの「AI 要約」用デフォルトスキルキーを返す。未設定なら 'summarize' (なければ先頭)。
     */
    public function getDefaultSummaryKey(User $user): ?string
    {
        $s = UserAiSkill::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('is_default_summary', true)
            ->first();
        if ($s) return $s->skill_key;
        // フォールバック: summarize → 先頭
        $s = UserAiSkill::where('user_id', $user->id)->where('is_active', true)
            ->where('skill_key', 'summarize')->first()
            ?? UserAiSkill::where('user_id', $user->id)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->first();
        return $s?->skill_key;
    }

    /**
     * ユーザーの「メール返信」用デフォルトスキルキーを返す。未設定なら 'reply' (なければ先頭)。
     */
    public function getDefaultReplyKey(User $user): ?string
    {
        $s = UserAiSkill::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('is_default_reply', true)
            ->first();
        if ($s) return $s->skill_key;
        $s = UserAiSkill::where('user_id', $user->id)->where('is_active', true)
            ->where('skill_key', 'reply')->first()
            ?? UserAiSkill::where('user_id', $user->id)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->first();
        return $s?->skill_key;
    }

    /**
     * ユーザーのスキルを全削除してデフォルトで再シードする。
     */
    public function resetToDefaults(User $user): void
    {
        UserAiSkill::where('user_id', $user->id)->delete();
        $this->seedDefaults($user);
    }

    /**
     * 新規スキル作成時の skill_key を生成 (重複しないようサフィックス付与)。
     */
    public function generateSkillKey(User $user, string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'skill';
        }
        $key = $base;
        $i = 2;
        while (UserAiSkill::where('user_id', $user->id)->where('skill_key', $key)->exists()) {
            $key = $base . '_' . $i;
            $i++;
            if ($i > 50) {
                $key = $base . '_' . Str::random(6);
                break;
            }
        }
        return $key;
    }
}
