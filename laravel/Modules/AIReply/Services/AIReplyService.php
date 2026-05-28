<?php

namespace Modules\AIReply\Services;

use App\Models\EmailThread;
use App\Services\RagApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\AIReply\Services\RagCollectionResolver;
use Modules\Knowledge\Services\NeuronService;

class AIReplyService
{
    public function __construct(
        protected NeuronService $neuron,
        protected RagCollectionResolver $collectionResolver,
        protected RagApiService $ragApi,
    ) {}

    /**
     * Generate a reply draft based on thread context and knowledge base.
     *
     * @param  EmailThread  $thread
     * @param  ?string  $provider   AI プロバイダー (ollama|claude|gemini)。null なら AiSetting デフォルト
     * @param  ?string  $model      モデル ID。null なら AiSetting デフォルト
     */
    public function generate(EmailThread $thread, ?string $provider = null, ?string $model = null)
    {
        $latestEmail = $thread->emails()->orderByDesc('received_at')->first();
        if (!$latestEmail) return null;

        // ▼ Phase 6-1: 顧客 (or 送信元ドメイン) から RAG コレクションを決定
        $collection = $this->collectionResolver->resolve($thread);

        // ▼ provider / model のフォールバック
        $aiSettings = \App\Models\AiSetting::getSettings();
        $provider = $provider ?: $aiSettings->default_provider;
        $model    = $model    ?: $aiSettings->default_model;

        // 1. ナレッジベースから関連情報を検索 (RAG)
        $query = $latestEmail->subject . " " . $latestEmail->plain_body;
        $context = $this->ragApi->query($query, 5, $provider, $model, $collection);

        // 2. プロンプトの構築 (指示書に基づいたセーフティルール含む)
        $prompt = $this->buildPrompt($latestEmail, $context);

        // 3. AI プロバイダ経由で生成 (collection を渡して Python 側で切替)
        $aiResult = $this->ragApi->query($prompt, 5, $provider, $model, $collection);

        // 4. ログの保存 (collection を含めて記録)
        $this->logGeneration($thread, $prompt, $aiResult, $collection, $provider, $model);

        $aiResult['provider'] = $provider;
        $aiResult['model']    = $model;
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

    protected function logGeneration(
        $thread,
        $prompt,
        $result,
        ?string $collection = null,
        ?string $provider = null,
        ?string $model = null,
    ) {
        $row = [
            'email_thread_id' => $thread->id,
            'user_id'         => Auth::id(),
            'provider'        => $provider ?: 'default',
            'prompt_summary'  => mb_substr($prompt, 0, 500),
            'generated_reply' => $result['answer'] ?? '',
            'confidence_score' => 85, // 実際はパースして取得
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        // model カラムが存在する場合のみセット
        try {
            if ($model && Schema::hasColumn('ext_ai_logs', 'model')) {
                $row['model'] = $model;
            }
        } catch (\Throwable) { /* noop */ }

        // Phase 6-3 で ext_ai_logs に collection カラムが追加される。あれば書き込む。
        try {
            if ($collection !== null && Schema::hasColumn('ext_ai_logs', 'collection')) {
                $row['collection'] = $collection;
            }
        } catch (\Throwable $e) {
            // テーブル未マイグレ等は無視
        }

        DB::table('ext_ai_logs')->insert($row);
    }
}
