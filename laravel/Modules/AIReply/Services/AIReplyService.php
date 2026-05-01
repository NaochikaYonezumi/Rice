<?php

namespace Modules\AIReply\Services;

use App\Models\EmailThread;
use Modules\Knowledge\Services\NeuronService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AIReplyService
{
    protected $neuron;

    public function __construct(NeuronService $neuron)
    {
        $this->neuron = $neuron;
    }

    /**
     * Generate a reply draft based on thread context and knowledge base.
     */
    public function generate(EmailThread $thread)
    {
        $latestEmail = $thread->emails()->orderByDesc('received_at')->first();
        if (!$latestEmail) return null;

        // 1. ナレッジベースから関連情報を検索 (RAG)
        $query = $latestEmail->subject . " " . $latestEmail->plain_body;
        $context = $this->neuron->query($query);

        // 2. プロンプトの構築 (指示書に基づいたセーフティルール含む)
        $prompt = $this->buildPrompt($latestEmail, $context);

        // 3. AI プロバイダ経由で生成 (現在は rag-python 側に LLM 呼び出しを委譲する想定)
        // 本来は OpenAI / Ollama の切り替えロジックが入る
        $aiResult = $this->neuron->query($prompt); // 暫定的に同じエンドポイントを使用

        // 4. ログの保存
        $this->logGeneration($thread, $prompt, $aiResult);

        return $aiResult;
    }

    protected function buildPrompt($email, $context)
    {
        $knowledge = $context['answer'] ?? 'No specific knowledge found.';
        
        return "あなたは「Rice」サポート担当者です。
以下の「ナレッジ情報」と「受信メール」を元に、丁寧な返信メールのドラフトを作成してください。

【禁止事項】
- 不確実な情報を断定的に伝えない。
- 個人情報の漏洩に繋がる表現を避ける。

【受信メール】
件名: {$email->subject}
本文: {$email->plain_body}

【ナレッジ情報】
{$knowledge}

【出力形式】
返信本文のみを出力してください。
最後に「確信度スコア: 0-100」を付与してください。";
    }

    protected function logGeneration($thread, $prompt, $result)
    {
        DB::table('ext_ai_logs')->insert([
            'email_thread_id' => $thread->id,
            'user_id'         => Auth::id(),
            'provider'        => 'default',
            'prompt_summary'  => mb_substr($prompt, 0, 500),
            'generated_reply' => $result['answer'] ?? '',
            'confidence_score' => 85, // 実際はパースして取得
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
