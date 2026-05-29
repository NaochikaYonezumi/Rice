<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAiChatMessage;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AiSetting;
use App\Models\EmailThread;
use App\Services\AiSkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI チャット (要約/返信案ブラッシュアップ) の REST エンドポイント.
 *
 * 1 ユーザ × 1 スレッド × 1 kind に対して 1 セッションが永続化される.
 * 同じスレッドを再訪したら続きから.
 */
class AiChatController extends Controller
{
    public function __construct(protected AiSkillService $skillService) {}

    /**
     * GET /threads/{thread}/ai-chat?kind=summary|reply
     *
     * 既存セッションがあれば全メッセージを返す. 無ければ session=null + messages=[].
     */
    public function show(Request $request, EmailThread $thread): JsonResponse
    {
        $this->authorizeThreadAccess($request, $thread);
        $kind = $this->validateKind($request->input('kind', 'summary'));
        $userId = $request->user()->id;

        $session = AiChatSession::where('user_id', $userId)
            ->where('thread_id', $thread->id)
            ->where('kind', $kind)
            ->first();

        if (!$session) {
            return response()->json([
                'session'  => null,
                'messages' => [],
                'thread'   => [
                    'id'      => $thread->id,
                    'subject' => $thread->subject,
                ],
            ]);
        }

        return response()->json([
            'session'  => $this->serializeSession($session),
            'messages' => $session->messages->map(fn($m) => $this->serializeMessage($m))->values(),
            'thread'   => [
                'id'      => $thread->id,
                'subject' => $thread->subject,
            ],
        ]);
    }

    /**
     * POST /threads/{thread}/ai-chat
     *
     * 初回ターン. 既存セッションが無ければ作成 (+system_prompt を AiSkill から決定).
     * user メッセージを append し, assistant の pending メッセージを作って Job を dispatch.
     *
     * body: { kind: summary|reply, message: string, provider?: string, model?: string, skill?: string }
     */
    public function start(Request $request, EmailThread $thread): JsonResponse
    {
        $this->authorizeThreadAccess($request, $thread);

        $data = $request->validate([
            'kind'     => 'nullable|string|in:summary,reply',
            'message'  => 'required|string|max:4000',
            'provider' => 'nullable|string|in:ollama,claude,gemini',
            'model'    => 'nullable|string|max:128',
            'skill'    => 'nullable|string|max:64',
        ]);

        $kind   = $data['kind'] ?? AiChatSession::KIND_SUMMARY;
        $userId = $request->user()->id;

        $session = AiChatSession::where('user_id', $userId)
            ->where('thread_id', $thread->id)
            ->where('kind', $kind)
            ->first();

        if (!$session) {
            // セッション初回作成: AiSkill から system_prompt を引き写してロックする.
            $skills = $this->skillService->getSkillsForUser($request->user(), $kind);
            $skillKey = $data['skill'] ?? null;
            if (!$skillKey) {
                $skillKey = $kind === AiChatSession::KIND_REPLY
                    ? ($this->skillService->getDefaultReplyKey($request->user()) ?? 'reply')
                    : ($this->skillService->getDefaultSummaryKey($request->user()) ?? 'summarize');
            }
            $selected = $skills[$skillKey] ?? array_values($skills)[0] ?? [
                'name'          => $kind === AiChatSession::KIND_REPLY ? '返信案' : '要約',
                'system_prompt' => $kind === AiChatSession::KIND_REPLY
                    ? "あなたはサポート窓口担当者の返信補助アシスタントです.\n"
                        . "対象は『返信対象メール』 (= スレッドの最新メール). スレッドの 【過去のやりとり】 は文脈把握のための参考情報です.\n\n"
                        . "■ 振る舞いの大原則:\n"
                        . "- ユーザが明示的に「返信を書いて」「返信案を出して」「下書きして」「返信文を作って」等を指示するまで, 完成した返信文の本文を生成してはいけません.\n"
                        . "- それまではチャットで会話してください. 例:\n"
                        . "    - スレッドの状況や論点を要約してあげる\n"
                        . "    - 返信に必要な情報 (金額, 日程, 確認事項など) が揃っているか確認する\n"
                        . "    - 「どんなトーンで返しますか?」「条件提示する/しない?」のように方針を質問する\n"
                        . "    - ユーザのアイデアに対して改善提案する\n"
                        . "- ユーザが明確に返信文を要求したターンに限り, 返信本文だけを出力します. 署名/件名/宛先などのヘッダは付けません.\n"
                        . "- 返信文を出力した直後でも, さらに「もう少し丁寧に」「短くして」のような改稿指示があれば改稿. ただし指示が無い限り再提案はしません.\n"
                        . "- 出力は日本語. ビジネスメールとして自然な敬体."
                    : "あなたはサポート窓口担当者です.\n以下のメールスレッドを日本語で要約してください.\n出力フォーマット:\n1. 概要 (3〜5行で何のスレッドか)\n2. 経緯 (時系列で 5〜8 行の箇条書き)\n3. 未解決事項 / ネクストアクション\n4. 重要な日付・金額・人物・固有名詞",
            ];

            $aiSettings = AiSetting::getSettings();
            $session = AiChatSession::create([
                'user_id'          => $userId,
                'thread_id'        => $thread->id,
                'kind'             => $kind,
                'provider'         => $data['provider'] ?? $aiSettings->default_provider,
                'model'            => $data['model']    ?? $aiSettings->default_model,
                'system_prompt'    => (string) ($selected['system_prompt'] ?? ''),
                'skill_key'        => $skillKey,
                'last_activity_at' => now(),
            ]);
        }

        return $this->appendUserAndDispatch($session, $data['message']);
    }

    /**
     * POST /ai-chat-sessions/{session}/messages
     *
     * フォローアップ (2 ターン目以降). body: { message: string }
     */
    public function followUp(Request $request, AiChatSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403, 'このチャットセッションへのアクセス権がありません.');
        }

        $data = $request->validate([
            'message'  => 'required|string|max:4000',
            'skill'    => 'nullable|string|max:64',
            'provider' => 'nullable|string|in:ollama,claude,gemini',
            'model'    => 'nullable|string|max:128',
        ]);

        // 途中で provider / model が変更されたらセッションに反映する.
        // (フロントのモデルピッカー変更が即時に効くようにするため)
        $updates = [];
        if (!empty($data['provider']) && $data['provider'] !== $session->provider) {
            $updates['provider'] = $data['provider'];
        }
        if (!empty($data['model']) && $data['model'] !== $session->model) {
            $updates['model'] = $data['model'];
        }
        // 途中でユーザがスキルを変えたら, セッションの system_prompt も差し替える.
        if (!empty($data['skill']) && $data['skill'] !== $session->skill_key) {
            $skills = $this->skillService->getSkillsForUser($request->user(), $session->kind);
            $selected = $skills[$data['skill']] ?? null;
            if ($selected) {
                $updates['skill_key']     = $data['skill'];
                $updates['system_prompt'] = (string) ($selected['system_prompt'] ?? $session->system_prompt);
            }
        }
        if (!empty($updates)) {
            $session->update($updates);
        }

        return $this->appendUserAndDispatch($session, $data['message']);
    }

    /**
     * DELETE /ai-chat-sessions/{session}
     *
     * セッション + 配下メッセージを破棄. UI の「会話をリセット」用.
     */
    public function destroy(Request $request, AiChatSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403, 'このチャットセッションへのアクセス権がありません.');
        }
        $session->delete();
        return response()->json(['status' => 'ok']);
    }

    /** スレッドの所有者 (owner_user_id) と本人を照合. 共有 (NULL) は誰でも OK. */
    protected function authorizeThreadAccess(Request $request, EmailThread $thread): void
    {
        if ($thread->owner_user_id !== null && $thread->owner_user_id !== $request->user()->id) {
            abort(403, 'このスレッドへのアクセス権がありません.');
        }
    }

    protected function validateKind(string $kind): string
    {
        return in_array($kind, [AiChatSession::KIND_SUMMARY, AiChatSession::KIND_REPLY], true)
            ? $kind
            : AiChatSession::KIND_SUMMARY;
    }

    /**
     * user メッセージを append + pending な assistant メッセージを作って Job を dispatch する共通処理.
     */
    protected function appendUserAndDispatch(AiChatSession $session, string $userMessage): JsonResponse
    {
        $user = AiChatMessage::create([
            'session_id' => $session->id,
            'role'       => AiChatMessage::ROLE_USER,
            'content'    => $userMessage,
            'status'     => AiChatMessage::STATUS_DONE,
        ]);
        $assistant = AiChatMessage::create([
            'session_id' => $session->id,
            'role'       => AiChatMessage::ROLE_ASSISTANT,
            'content'    => '',
            'status'     => AiChatMessage::STATUS_PENDING,
        ]);
        $session->update(['last_activity_at' => now()]);

        ProcessAiChatMessage::dispatch($assistant->id);

        return response()->json([
            'session'   => $this->serializeSession($session->fresh()),
            'user'      => $this->serializeMessage($user),
            'assistant' => $this->serializeMessage($assistant),
        ]);
    }

    protected function serializeSession(AiChatSession $session): array
    {
        return [
            'id'               => $session->id,
            'thread_id'        => $session->thread_id,
            'kind'             => $session->kind,
            'provider'         => $session->provider,
            'model'            => $session->model,
            'skill_key'        => $session->skill_key,
            'last_activity_at' => $session->last_activity_at?->toIso8601String(),
        ];
    }

    protected function serializeMessage(AiChatMessage $m): array
    {
        return [
            'id'            => $m->id,
            'role'          => $m->role,
            'content'       => $m->content,
            'status'        => $m->status,
            'error_code'    => $m->error_code,
            'error_message' => $m->error_message,
            'elapsed_ms'    => $m->elapsed_ms,
            'created_at'    => $m->created_at?->toIso8601String(),
        ];
    }
}
