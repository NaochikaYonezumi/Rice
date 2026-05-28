<?php

namespace App\Jobs;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\Email;
use App\Services\RagApiException;
use App\Services\RagApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI チャットの 1 ターン (= pending な assistant メッセージ) を処理する.
 *
 * フロー:
 *   1) session_id と pending な assistant メッセージ id を受け取る
 *   2) スレッド本文 (= 毎回最新) + 履歴 + 最新 user メッセージ を組み立てて prompt を作る
 *   3) RagApiService::query() で LLM を呼ぶ
 *   4) 結果を assistant メッセージに書き戻す (content + status)
 *
 * 過去メッセージは履歴用にせいぜい直近 10 ターンまで含める. メール本文は冒頭の
 * system ブロックに 1 度だけ入れて, 履歴側には繰り返さない (= トークン節約).
 */
class ProcessAiChatMessage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 1;

    /** 履歴として含める user/assistant ペアの最大数 (各メッセージ単位ではなくペア数). */
    private const HISTORY_PAIR_LIMIT = 10;

    /** メール 1 通あたりの本文を切り詰める文字数. summarizeThread() と同等. */
    private const EMAIL_BODY_LIMIT = 800;

    public function __construct(public int $assistantMessageId) {}

    public function handle(RagApiService $ragApi): void
    {
        $assistant = AiChatMessage::find($this->assistantMessageId);
        if (!$assistant || $assistant->role !== AiChatMessage::ROLE_ASSISTANT) {
            return;
        }
        $session = AiChatSession::find($assistant->session_id);
        if (!$session) {
            $assistant->status        = AiChatMessage::STATUS_ERROR;
            $assistant->error_code    = 'session_not_found';
            $assistant->error_message = '対応するチャットセッションが存在しません.';
            $assistant->save();
            return;
        }

        $startedAt = microtime(true);
        try {
            $prompt = $this->buildPrompt($session, $assistant);

            $result = $ragApi->query(
                $prompt,
                3,
                $session->provider,
                $session->model,
            );
            $assistant->content    = (string) ($result['answer'] ?? '');
            $assistant->status     = AiChatMessage::STATUS_DONE;
            $assistant->elapsed_ms = (int) round((microtime(true) - $startedAt) * 1000);
            $assistant->save();

            $session->update(['last_activity_at' => now()]);
        } catch (RagApiException $e) {
            $assistant->status        = AiChatMessage::STATUS_ERROR;
            $assistant->error_code    = $e->errorCode;
            $assistant->error_message = $e->getMessage() . ($e->raw ? "\n\n[詳細]\n" . mb_substr($e->raw, 0, 600) : '');
            $assistant->save();
        } catch (\Throwable $e) {
            Log::error('ProcessAiChatMessage failed', [
                'assistant_id' => $assistant->id,
                'session_id'   => $session->id,
                'error'        => $e->getMessage(),
            ]);
            $assistant->status        = AiChatMessage::STATUS_ERROR;
            $assistant->error_code    = 'internal_error';
            $assistant->error_message = $e->getMessage();
            $assistant->save();
        }
    }

    /**
     * セッション + 履歴 + スレッド本文を結合した 1 つの prompt 文字列を組み立てる.
     * RagApiService::query() は string ベースなので, ここで chat 風に整形する.
     */
    protected function buildPrompt(AiChatSession $session, AiChatMessage $assistant): string
    {
        $thread = $session->thread;
        $threadSubject = $thread?->subject ?: '(件名なし)';

        // スレッド本文 (毎回最新の email をシリアライズ) — system context に固定で 1 回だけ入れる.
        $threadContext = '';
        if ($thread) {
            $emails = Email::where('thread_id', $thread->id)
                ->whereNull('trashed_at')
                ->orderBy('received_at')
                ->limit(50)
                ->get();
            foreach ($emails as $i => $e) {
                $threadContext .= "[#" . ($i + 1) . "] ";
                $threadContext .= "From: " . ($e->from_label ?: $e->from_address ?: '不明') . "\n";
                $threadContext .= "To: "   . ($e->to_address ?: '—') . "\n";
                if ($e->cc) $threadContext .= "Cc: " . $e->cc . "\n";
                $threadContext .= "Date: " . ($e->received_at?->format('Y/m/d H:i') ?: '—') . "\n";
                $threadContext .= "Subject: " . ($e->subject ?: '(件名なし)') . "\n";
                $threadContext .= "Body:\n" . Str::limit((string) $e->plain_body, self::EMAIL_BODY_LIMIT, '...(省略)') . "\n";
                $threadContext .= "----\n";
            }
        }

        // 履歴 (今回処理する assistant より前のメッセージ).
        // 同セッション内で完了済みの user/assistant ペアを直近 N 件まで.
        $history = AiChatMessage::where('session_id', $session->id)
            ->where('id', '<', $assistant->id)
            ->where('status', AiChatMessage::STATUS_DONE)
            ->orderByDesc('id')
            ->limit(self::HISTORY_PAIR_LIMIT * 2)
            ->get()
            ->reverse()
            ->values();

        $historyBlock = '';
        foreach ($history as $m) {
            $label = $m->role === AiChatMessage::ROLE_USER ? 'ユーザ' : 'アシスタント';
            $historyBlock .= "[{$label}] " . trim((string) $m->content) . "\n\n";
        }

        // 「今ターン」のユーザ入力 = この assistant の直前にある最新の user メッセージ.
        $latestUser = AiChatMessage::where('session_id', $session->id)
            ->where('id', '<', $assistant->id)
            ->where('role', AiChatMessage::ROLE_USER)
            ->orderByDesc('id')
            ->first();
        $latestUserContent = trim((string) ($latestUser->content ?? ''));

        // 上記履歴から「今回の user メッセージ」を 1 件だけ除外 (= 二重投入の防止).
        // この履歴ブロックは「過去のやりとり」を表現するためのものなので,
        // ターン本体の user 入力は別ブロックで明示する.
        if ($latestUser) {
            $historyBlock = str_replace(
                "[ユーザ] " . trim((string) $latestUser->content) . "\n\n",
                '',
                $historyBlock
            );
        }

        $kindLabel = $session->kind === AiChatSession::KIND_REPLY ? '返信案ブラッシュアップ' : '要約ブラッシュアップ';

        $prompt  = "【システム指示】\n" . (string) $session->system_prompt . "\n\n";
        $prompt .= "【モード】" . $kindLabel . "\n";
        $prompt .= "【スレッド件名】" . $threadSubject . "\n";
        if ($thread?->ticket_number) {
            $prompt .= "【チケット番号】[#" . $thread->ticket_number . "]\n";
        }
        $prompt .= "\n";
        if ($threadContext !== '') {
            $prompt .= "【スレッド本文 (時系列)】\n" . $threadContext . "\n";
        }
        if ($historyBlock !== '') {
            $prompt .= "【これまでの対話】\n" . $historyBlock . "\n";
        }
        $prompt .= "【今回のユーザ指示】\n" . ($latestUserContent !== '' ? $latestUserContent : '(空)') . "\n\n";
        $prompt .= "【出力】上記指示に従ってアシスタントとして応答してください. 余計な前置きや挨拶は不要.";

        return $prompt;
    }
}
