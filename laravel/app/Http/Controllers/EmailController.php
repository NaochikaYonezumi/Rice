<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\MailSetting;
use App\Models\PendingEmail;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use App\Services\AiSkillService;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\MailClient\Services\EmailFetcher;

class EmailController extends Controller
{
    public function __construct(
        private RagApiService $ragApi,
        private AiSkillService $skillService,
    ) {}

    public function index()
    {
        return view('emails.index', [
            'isPinnedView'        => false,
            'userAiSkills'        => $this->skillService->getSkillsForUser(auth()->user()),
            'userSummarySkills'   => $this->skillService->getSkillsForUser(auth()->user(), 'summary'),
            'userReplySkills'     => $this->skillService->getSkillsForUser(auth()->user(), 'reply'),
        ]);
    }

    public function pinned()
    {
        return view('emails.index', [
            'isPinnedView'        => true,
            'userAiSkills'        => $this->skillService->getSkillsForUser(auth()->user()),
            'userSummarySkills'   => $this->skillService->getSkillsForUser(auth()->user(), 'summary'),
            'userReplySkills'     => $this->skillService->getSkillsForUser(auth()->user(), 'reply'),
        ]);
    }

    /**
     * 新規作成ウィンドウ (作成専用画面)
     */
    public function composeWindow()
    {
        $settings = MailSetting::getSettings();
        $defaultFrom = $settings->smtp_from_address ?? '';

        return view('emails.compose-window', [
            'mode'         => 'compose',
            'email'        => null,
            'thread'       => null,
            'emails'       => [],
            'defaultFrom'  => $defaultFrom,
            'replyTo'      => '',
            'replyCc'      => '',
            'replyBcc'     => '',
            'replySubject' => '',
            'approvers'    => $this->getApproverCandidates(),
            'userAiSkills' => $this->skillService->getSkillsForUser(auth()->user(), 'reply'),
            'sendPolicy'   => $settings->send_policy ?? 'flexible',
        ]);
    }

    /**
     * プロンプト中の /コレクション 参照を抽出し、参照すべきナレッジソース一覧を返す。
     *
     * 戻り値: [
     *   'collections' => ['モビリティ', 'マニュアル'],  // 検出されたコレクション名
     *   'references_block' => "【参照すべきコレクション】\n■ コレクション: モビリティ\n  - [url] ...",
     * ]
     * 該当ソースが 1 件もなければ references_block は空文字。
     */
    private function expandCollectionReferences(string ...$texts): array
    {
        $combined = implode("\n", $texts);
        preg_match_all('/(^|\s)\/([^\s\/\\\\#?&]+)/u', $combined, $matches);
        $tokens = collect($matches[2] ?? [])->filter()->unique()->values();
        if ($tokens->isEmpty()) {
            return ['collections' => [], 'references_block' => ''];
        }

        $refs = [];
        foreach ($tokens as $token) {
            $sources = \App\Models\ScrapedUrl::where('collection', $token)
                ->where('status', 'ok')
                ->orderByDesc('chunks_indexed')
                ->limit(20)
                ->get();
            if ($sources->isEmpty()) continue;
            $refs[$token] = $sources;
        }
        if (empty($refs)) {
            return ['collections' => $tokens->all(), 'references_block' => ''];
        }

        $block  = "【参照すべきコレクション】\n";
        $block .= "ユーザーは以下のコレクションを参照するよう指示しています。回答時はこれらのソースに含まれる情報を優先的に使ってください。\n";
        foreach ($refs as $colName => $sources) {
            $block .= "\n■ コレクション: {$colName}\n";
            foreach ($sources as $s) {
                $title = $s->title ?: $s->url;
                $type = $s->source_type ?: 'url';
                $typeLabel = ['url' => 'URL', 'file' => 'ファイル', 'email' => 'メール'][$type] ?? $type;
                $line = "  - [{$typeLabel}] " . \Illuminate\Support\Str::limit($title, 100);
                if ($type === 'url') {
                    $line .= " ({$s->url})";
                }
                $block .= $line . "\n";
            }
        }
        return [
            'collections' => array_keys($refs),
            'references_block' => $block,
        ];
    }

    /**
     * 承認者候補のユーザー一覧 (自分以外)
     */
    private function getApproverCandidates(): array
    {
        return User::where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->role,
            ])->all();
    }

    /**
     * 返信 / 全員返信ウィンドウ (作成専用画面)
     * ?all=1 で全員返信
     */
    public function replyWindow(Email $email, Request $request)
    {
        $email->load(['thread.customer', 'thread.assignee', 'attachments']);
        $isReplyAll = $request->boolean('all');

        $thread = $email->thread;
        $threadIds = $thread ? [$thread->id] : [];
        if ($thread) {
            $merged = \App\Models\ThreadMerge::where('target_thread_id', $thread->id)
                ->pluck('source_thread_id_original')->toArray();
            $threadIds = array_merge($threadIds, $merged);
        }

        $threadEmails = !empty($threadIds)
            ? Email::whereIn('thread_id', $threadIds)
                ->with('attachments')
                ->orderBy('received_at', 'desc')
                ->get()
                ->map(fn($e) => [
                    'id'           => $e->id,
                    'thread_id'    => $e->thread_id,
                    'subject'      => $e->subject,
                    'from_label'   => $e->from_label,
                    'from_address' => $e->from_address,
                    'to_address'   => $e->to_address,
                    'cc'           => $e->cc,
                    'plain_body'   => $e->plain_body,
                    'received_at'  => $e->received_at?->format('Y/m/d H:i'),
                    'attachments'  => $e->attachments->map(fn($a) => [
                        'id'       => $a->id,
                        'filename' => $a->filename,
                        'url'      => route('attachments.download', $a->id),
                    ])->values(),
                ])->values()->all()
            : [];

        $settings = MailSetting::getSettings();
        $defaultFrom = $settings->smtp_from_address ?? '';

        // 返信フォームの初期値
        $toAddresses = $email->to_address
            ? array_values(array_filter(array_map('trim', explode(',', $email->to_address))))
            : [];
        $fromForReply = $toAddresses[0] ?? $defaultFrom;

        $replyTo = $email->from_address ?? '';
        $replyCc = '';
        if ($isReplyAll) {
            $ccAddresses = $email->cc
                ? array_values(array_filter(array_map('trim', explode(',', $email->cc))))
                : [];
            $ccAddresses = array_values(array_filter(
                $ccAddresses,
                fn($c) => !in_array($c, $toAddresses, true) && $c !== $replyTo
            ));
            $replyCc = implode(', ', $ccAddresses);
        }

        // 返信件名: 既存件名から内部チケット番号タグ ([#TICKET-XXXXXX] / [#RICE-XXXXXX]) を剥がし、
        // 必要に応じて "Re: " を付ける。チケット番号を送信件名に含めない方針に統一するため.
        $subject = (string) ($email->subject ?? '');
        $subject = preg_replace(\App\Models\EmailThread::TICKET_REGEX, '', $subject) ?? $subject;
        $subject = trim($subject);
        $replySubject = (str_starts_with($subject, 'Re:') || str_starts_with($subject, 're:'))
            ? $subject
            : 'Re: ' . $subject;

        return view('emails.compose-window', [
            'mode'         => $isReplyAll ? 'reply_all' : 'reply',
            'email'        => [
                'id'           => $email->id,
                'thread_id'    => $email->thread_id,
                'subject'      => $email->subject,
                'from_label'   => $email->from_label,
                'from_address' => $email->from_address,
                'to_address'   => $email->to_address,
                'cc'           => $email->cc,
                'plain_body'   => $email->plain_body,
                'received_at'  => $email->received_at?->format('Y/m/d H:i'),
                'attachments'  => $email->attachments->map(fn($a) => [
                    'id'       => $a->id,
                    'filename' => $a->filename,
                    'url'      => route('attachments.download', $a->id),
                ])->values(),
            ],
            'thread'       => $thread ? [
                'id'            => $thread->id,
                'subject'       => $thread->subject,
                'ticket_number' => $thread->ticket_number,
                'status'        => $thread->status,
                'customer'      => $thread->customer ? ['id' => $thread->customer->id, 'name' => $thread->customer->name] : null,
                'assignee'      => $thread->assignee ? ['id' => $thread->assignee->id, 'name' => $thread->assignee->name] : null,
            ] : null,
            'emails'       => $threadEmails,
            'defaultFrom'  => $fromForReply,
            'replyTo'      => $replyTo,
            'replyCc'      => $replyCc,
            'replyBcc'     => '',
            'replySubject' => $replySubject,
            'approvers'    => $this->getApproverCandidates(),
            'userAiSkills' => $this->skillService->getSkillsForUser(auth()->user(), 'reply'),
            'sendPolicy'   => \App\Models\MailSetting::getSettings()->send_policy ?? 'flexible',
        ]);
    }

    /**
     * 転送 (Forward) 用のコンポーズウィンドウを開く.
     *
     * replyWindow と同じ compose-window.blade.php を使うが、以下が異なる:
     *   - mode = 'forward'
     *   - 件名 = "Fwd: " + (元件名の Re:/Fwd: と チケット番号タグを剥がしたもの)
     *   - 宛先 (To) は空 (ユーザが入力)
     *   - 本文に "---------- Forwarded message ----------" 形式の引用を入れる
     *   - 元メールの添付ファイルを「継承候補」として全て選択済み状態で表示し、
     *     ユーザが個別にチェックを外せる (= 引き継がない選択可).
     *
     * 添付の実体コピーは submit (forward()) 時に行う. ここでは ID + メタ情報のみ渡す.
     */
    public function forwardWindow(Email $email, Request $request)
    {
        $email->load(['thread.customer', 'thread.assignee', 'attachments']);

        $thread = $email->thread;
        $threadIds = $thread ? [$thread->id] : [];
        if ($thread) {
            $merged = \App\Models\ThreadMerge::where('target_thread_id', $thread->id)
                ->pluck('source_thread_id_original')->toArray();
            $threadIds = array_merge($threadIds, $merged);
        }
        $threadEmails = !empty($threadIds)
            ? Email::whereIn('thread_id', $threadIds)
                ->with('attachments')
                ->orderBy('received_at', 'desc')
                ->get()
                ->map(fn($e) => [
                    'id'           => $e->id,
                    'thread_id'    => $e->thread_id,
                    'subject'      => $e->subject,
                    'from_label'   => $e->from_label,
                    'from_address' => $e->from_address,
                    'to_address'   => $e->to_address,
                    'cc'           => $e->cc,
                    'plain_body'   => $e->plain_body,
                    'received_at'  => $e->received_at?->format('Y/m/d H:i'),
                    'attachments'  => $e->attachments->map(fn($a) => [
                        'id'       => $a->id,
                        'filename' => $a->filename,
                        'url'      => route('attachments.download', $a->id),
                    ])->values(),
                ])->values()->all()
            : [];

        // 件名: 既存件名から内部チケット番号タグ + Re:/Fwd: 連鎖プレフィックスを剥がして "Fwd: " を頭に付ける.
        // 既に "Fwd: " で始まる場合はそのまま (= 二重 Fwd を避ける).
        $subject = (string) ($email->subject ?? '');
        $subject = preg_replace(\App\Models\EmailThread::TICKET_REGEX, '', $subject) ?? $subject;
        $subject = preg_replace('/^(\s*(?:re|fwd|fw)\s*(?::|:)\s*)+/iu', '', $subject) ?? $subject;
        $subject = trim($subject);
        $fwdSubject = (stripos($subject, 'Fwd:') === 0) ? $subject : 'Fwd: ' . $subject;

        // 引用本文の構築 (プレーンテキスト). plain_body を採用.
        $quoted = "\n\n---------- Forwarded message ----------\n";
        $quoted .= 'From: ' . ($email->from_label ?: ($email->from_address ?? '-')) . "\n";
        $quoted .= 'Date: ' . ($email->received_at?->format('Y/m/d H:i') ?: '-') . "\n";
        $quoted .= 'Subject: ' . ($email->subject ?: '(件名なし)') . "\n";
        if ($email->to_address) $quoted .= 'To: ' . $email->to_address . "\n";
        if ($email->cc)         $quoted .= 'Cc: ' . $email->cc . "\n";
        $quoted .= "\n";
        $quoted .= (string) $email->plain_body;

        // 継承添付: 元メールの全添付を「引き継ぎ候補」として渡す.
        // フロント側で個別チェックを外せる. 実体コピーは submit 時に発生.
        $inheritedAttachments = $email->attachments->map(fn($a) => [
            'id'        => $a->id,
            'filename'  => $a->filename,
            'mime_type' => $a->mime_type,
            'size'      => (int) $a->size,
            'url'       => route('attachments.download', $a->id),
        ])->values()->all();

        $settings = MailSetting::getSettings();
        $defaultFrom = $settings->smtp_from_address ?? '';
        $toAddresses = $email->to_address
            ? array_values(array_filter(array_map('trim', explode(',', $email->to_address))))
            : [];
        $fromForReply = $toAddresses[0] ?? $defaultFrom;

        return view('emails.compose-window', [
            'mode'         => 'forward',
            'email'        => [
                'id'           => $email->id,
                'thread_id'    => $email->thread_id,
                'subject'      => $email->subject,
                'from_label'   => $email->from_label,
                'from_address' => $email->from_address,
                'to_address'   => $email->to_address,
                'cc'           => $email->cc,
                'plain_body'   => $email->plain_body,
                'received_at'  => $email->received_at?->format('Y/m/d H:i'),
                'attachments'  => $email->attachments->map(fn($a) => [
                    'id'       => $a->id,
                    'filename' => $a->filename,
                    'url'      => route('attachments.download', $a->id),
                ])->values(),
            ],
            'thread'       => $thread ? [
                'id'            => $thread->id,
                'subject'       => $thread->subject,
                'ticket_number' => $thread->ticket_number,
                'status'        => $thread->status,
                'customer'      => $thread->customer ? ['id' => $thread->customer->id, 'name' => $thread->customer->name] : null,
                'assignee'      => $thread->assignee ? ['id' => $thread->assignee->id, 'name' => $thread->assignee->name] : null,
            ] : null,
            'emails'       => $threadEmails,
            'defaultFrom'  => $fromForReply,
            'replyTo'      => '',
            'replyCc'      => '',
            'replyBcc'     => '',
            'replySubject' => $fwdSubject,
            'replyBody'    => $quoted,
            'inheritedAttachments' => $inheritedAttachments,
            'approvers'    => $this->getApproverCandidates(),
            'userAiSkills' => $this->skillService->getSkillsForUser(auth()->user(), 'reply'),
            'sendPolicy'   => $settings->send_policy ?? 'flexible',
        ]);
    }

    /**
     * 直近の取得ステータス (成功/失敗 + 連続失敗回数) を返す。
     * UI 側 (ページロード時 / バックグラウンド polling) で
     * 「サイレント失敗が起きていないか」を継続監視するために使う。
     */
    public function fetchStatus(): JsonResponse
    {
        $s = \App\Models\MailSetting::getSettings();
        return response()->json([
            'last_fetch_at'         => $s->last_fetch_at?->format('Y-m-d H:i:s'),
            'last_fetch_success_at' => $s->last_fetch_success_at?->format('Y-m-d H:i:s'),
            'last_fetch_error_at'   => $s->last_fetch_error_at?->format('Y-m-d H:i:s'),
            'last_fetch_error'      => $s->last_fetch_error,
            'last_fetch_count'      => (int) $s->last_fetch_count,
            'consecutive_failures'  => (int) $s->consecutive_failures,
            // 現在「失敗状態か」: 直近の試行が失敗 (success_at が error_at より古い、
            // または success_at がそもそも無い) なら true
            'is_failing' => (function () use ($s) {
                if (!$s->last_fetch_error) return false;
                if (!$s->last_fetch_success_at) return true;
                return $s->last_fetch_error_at && $s->last_fetch_error_at->gt($s->last_fetch_success_at);
            })(),
        ]);
    }

    public function fetch(EmailFetcher $fetcher): JsonResponse
    {
        $settings = \App\Models\MailSetting::getSettings();
        try {
            $count = $fetcher->fetch();
            $errInfo = $fetcher->getLastErrors();
            // 取得成功 (個別エラーがあっても全体としては成功扱い)
            $settings->recordFetchSuccess((int) $count, $errInfo['errors'] ?? []);
            return response()->json([
                'status'       => 'ok',
                'count'        => $count,
                // 既存メールのうち「前回うまく取得できなかった」フィールドを再パースで更新した件数。
                // 新規取り込み (count) とは別軸で UI に出すための値。
                'backfilled'   => (int) ($errInfo['backfilled'] ?? 0),
                'error_count'  => (int) ($errInfo['count'] ?? 0),
                'errors'       => $errInfo['errors'] ?? [],
                'consecutive_failures' => 0,
            ]);
        } catch (\Throwable $e) {
            // 全体エラー (接続不能 / 認証失敗 / 取得中の中断) はメインメッセージ + 部分成功 + 個別エラーも返す
            $errInfo = $fetcher->getLastErrors();
            $settings->recordFetchFailure($e->getMessage());
            return response()->json([
                'status'           => 'error',
                'error'            => $e->getMessage(),
                'connection_error' => $errInfo['connection_error'] ?? null,
                'backfilled'       => (int) ($errInfo['backfilled'] ?? 0),
                'error_count'      => (int) ($errInfo['count'] ?? 0),
                'errors'           => $errInfo['errors'] ?? [],
                'consecutive_failures' => (int) $settings->fresh()->consecutive_failures,
            ], 500);
        }
    }

    // AIアシスタントによる生成 (スキル選択・コンテキスト強化)
    public function askAi(Request $request, Email $email): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string|in:ollama,claude,gemini',
            'model'    => 'nullable|string|max:128',
        ]);
        $userPrompt = trim($request->input('prompt', ''));
        $skills     = $this->skillService->getSkillsForUser(auth()->user());
        $skillKey   = $request->input('skill') ?: $this->skillService->getDefaultReplyKey(auth()->user()) ?? 'reply';
        $selectedSkill = $skills[$skillKey] ?? ($skills['reply'] ?? array_values($skills)[0] ?? ['name' => '汎用', 'system_prompt' => '']);

        // 並列コンテキスト取得 (タイムアウトでハングを防ぐ)
        try {
            $responses = Http::pool(fn ($pool) => [
                $pool->as('kb')->timeout(8)->connectTimeout(3)
                    ->get("http://rag-api:8000/query", ['query' => $email->subject . " " . Str::limit($email->body_text, 100)]),
                $pool->as('reports')->timeout(8)->connectTimeout(3)
                    ->get("http://rag-api:8000/reports", ['query' => $email->subject]),
            ]);
            $kbContent     = ($responses['kb']     instanceof \Illuminate\Http\Client\Response && $responses['kb']->ok())     ? ($responses['kb']->json()['answer']      ?? '') : '';
            $reportContent = ($responses['reports'] instanceof \Illuminate\Http\Client\Response && $responses['reports']->ok()) ? ($responses['reports']->json()['content'] ?? '') : '';
        } catch (\Throwable $e) {
            $kbContent = '';
            $reportContent = '';
        }

        // スレッド履歴の構築
        $threadContext = "";
        if ($email->thread) {
            $threadEmails = $email->thread->emails()->orderBy('received_at', 'desc')->limit(5)->get()->reverse();
            foreach ($threadEmails as $te) {
                $threadContext .= "From: {$te->from_label}\nDate: {$te->received_at}\nBody: " . Str::limit($te->body_text, 300) . "\n---\n";
            }
        }

        $aiSettings = AiSetting::getSettings();
        // 担当者情報は「ログインユーザー個人設定 → 全体 AI 設定 → ハードコード」の順で解決
        $authUser  = auth()->user();
        $agentName = $authUser?->resolvedDisplayName() ?: ($aiSettings->agent_name ?: '米住 直親');
        $signature = $authUser?->resolvedSignature()   ?: ($aiSettings->agent_signature ?: "---\nPaperCutサポート窓口\n{$agentName}");

        // /コレクション 参照を抽出してプロンプトに注入
        $colRef = $this->expandCollectionReferences(
            $userPrompt,
            $selectedSkill['system_prompt'] ?? ''
        );

        $finalPrompt = "【システム指示】\n{$selectedSkill['system_prompt']}\n\n";
        if (!empty($colRef['references_block'])) {
            $finalPrompt .= $colRef['references_block'] . "\n";
        }
        $finalPrompt .= "【コンテキスト: ナレッジベース】\n{$kbContent}\n\n";
        $finalPrompt .= "【コンテキスト: 関連レポート】\n{$reportContent}\n\n";
        $finalPrompt .= "【コンテキスト: スレッド履歴】\n{$threadContext}\n\n";
        $finalPrompt .= "【ユーザーの追加指示】\n" . ($userPrompt ?: "特になし") . "\n\n";
        $finalPrompt .= "【担当者情報】\n名前: {$agentName}\n署名:\n{$signature}\n\n";
        $finalPrompt .= "【制約】必ず日本語で回答してください。";

        // PIIマスキング処理
        if ($request->boolean('mask_pii')) {
            $patterns = [
                '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '[EMAIL]',
                '/(\d{2,4}-\d{2,4}-\d{4})/' => '[PHONE]',
                '/(\d{3}-\d{4})/' => '[ZIP_CODE]',
            ];
            $finalPrompt = preg_replace(array_keys($patterns), array_values($patterns), $finalPrompt);
        }

        // RAG API経由で生成 (provider/model はリクエスト指定があればそれを優先)
        $provider = $request->input('provider') ?: $aiSettings->default_provider;
        $model    = $request->input('model')    ?: $aiSettings->default_model;

        // 非同期化: タスクを作って Job に投げ、task_id を返す
        $task = \App\Models\AiTask::create([
            'user_id'   => auth()->id(),
            'thread_id' => $email->thread_id,
            'task_type' => \App\Models\AiTask::TYPE_REPLY_ASSIST,
            'status'    => \App\Models\AiTask::STATUS_PENDING,
            'provider'  => $provider,
            'model'     => $model,
            'prompt'    => $finalPrompt,
            'result_meta' => [
                'skill_used' => $selectedSkill['name'],
                'sources' => [
                    'kb'      => !empty($kbContent),
                    'reports' => !empty($reportContent),
                ],
            ],
        ]);
        \App\Jobs\ProcessAiTask::dispatch($task->id);

        return response()->json([
            'status'     => 'pending',
            'task_id'    => $task->id,
            'skill_used' => $selectedSkill['name'],
            'sources'    => [
                'kb'      => !empty($kbContent),
                'reports' => !empty($reportContent),
            ],
            'provider'   => $provider,
            'model'      => $model,
        ]);
    }

    /**
     * 新規作成画面用の AI アシスタント (返信対象なし)
     *   入力: subject, body, to, prompt, skill, mask_pii
     */
    public function askAiCompose(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string|in:ollama,claude,gemini',
            'model'    => 'nullable|string|max:128',
        ]);
        $userPrompt = trim($request->input('prompt', ''));
        $subject    = trim((string) $request->input('subject', ''));
        $body       = trim((string) $request->input('body', ''));
        $to         = trim((string) $request->input('to', ''));
        $skills     = $this->skillService->getSkillsForUser(auth()->user());
        $skillKey   = $request->input('skill') ?: $this->skillService->getDefaultReplyKey(auth()->user()) ?? 'reply';
        $selectedSkill = $skills[$skillKey] ?? ($skills['reply'] ?? array_values($skills)[0] ?? ['name' => '汎用', 'system_prompt' => '']);

        // ナレッジ + レポート (キーワード = 件名 + ユーザー指示 のうち先頭)
        $queryStr = trim(($subject !== '' ? $subject : '') . ' ' . Str::limit($userPrompt, 80));
        try {
            $responses = Http::pool(fn ($pool) => [
                $pool->as('kb')->timeout(8)->connectTimeout(3)
                    ->get("http://rag-api:8000/query", ['query' => $queryStr]),
                $pool->as('reports')->timeout(8)->connectTimeout(3)
                    ->get("http://rag-api:8000/reports", ['query' => $queryStr]),
            ]);
            $kbContent     = ($responses['kb']     instanceof \Illuminate\Http\Client\Response && $responses['kb']->ok())     ? ($responses['kb']->json()['answer']      ?? '') : '';
            $reportContent = ($responses['reports'] instanceof \Illuminate\Http\Client\Response && $responses['reports']->ok()) ? ($responses['reports']->json()['content'] ?? '') : '';
        } catch (\Throwable $e) {
            $kbContent = '';
            $reportContent = '';
        }

        $aiSettings = AiSetting::getSettings();
        $authUser  = auth()->user();
        $agentName = $authUser?->resolvedDisplayName() ?: ($aiSettings->agent_name ?: '米住 直親');
        $signature = $authUser?->resolvedSignature()   ?: ($aiSettings->agent_signature ?: "---\nPaperCutサポート窓口\n{$agentName}");

        $colRef = $this->expandCollectionReferences(
            $userPrompt,
            $selectedSkill['system_prompt'] ?? ''
        );

        $finalPrompt  = "【システム指示】\n{$selectedSkill['system_prompt']}\n\n";
        $finalPrompt .= "【モード】新規作成 (返信対象なし)\n\n";
        if (!empty($colRef['references_block'])) {
            $finalPrompt .= $colRef['references_block'] . "\n";
        }
        $finalPrompt .= "【コンテキスト: ナレッジベース】\n{$kbContent}\n\n";
        $finalPrompt .= "【コンテキスト: 関連レポート】\n{$reportContent}\n\n";
        $finalPrompt .= "【作成中のメール】\n";
        $finalPrompt .= "宛先: " . ($to !== '' ? $to : '(未入力)') . "\n";
        $finalPrompt .= "件名: " . ($subject !== '' ? $subject : '(未入力)') . "\n";
        $finalPrompt .= "本文 (現状):\n" . ($body !== '' ? $body : '(空)') . "\n\n";
        $finalPrompt .= "【ユーザーの追加指示】\n" . ($userPrompt !== '' ? $userPrompt : '特になし') . "\n\n";
        $finalPrompt .= "【担当者情報】\n名前: {$agentName}\n署名:\n{$signature}\n\n";
        $finalPrompt .= "【制約】必ず日本語で回答してください。新規メールの本文として直接貼り付けられる形式で出力してください。";

        if ($request->boolean('mask_pii')) {
            $patterns = [
                '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '[EMAIL]',
                '/(\d{2,4}-\d{2,4}-\d{4})/' => '[PHONE]',
                '/(\d{3}-\d{4})/' => '[ZIP_CODE]',
            ];
            $finalPrompt = preg_replace(array_keys($patterns), array_values($patterns), $finalPrompt);
        }

        $provider = $request->input('provider') ?: $aiSettings->default_provider;
        $model    = $request->input('model')    ?: $aiSettings->default_model;

        // 非同期化
        $task = \App\Models\AiTask::create([
            'user_id'   => auth()->id(),
            'thread_id' => null,
            'task_type' => \App\Models\AiTask::TYPE_COMPOSE_ASSIST,
            'status'    => \App\Models\AiTask::STATUS_PENDING,
            'provider'  => $provider,
            'model'     => $model,
            'prompt'    => $finalPrompt,
            'result_meta' => [
                'skill_used' => $selectedSkill['name'],
                'sources' => [
                    'kb'      => !empty($kbContent),
                    'reports' => !empty($reportContent),
                ],
            ],
        ]);
        \App\Jobs\ProcessAiTask::dispatch($task->id);

        return response()->json([
            'status'     => 'pending',
            'task_id'    => $task->id,
            'skill_used' => $selectedSkill['name'],
            'sources'    => [
                'kb'      => !empty($kbContent),
                'reports' => !empty($reportContent),
            ],
            'provider' => $provider,
            'model'    => $model,
        ]);
    }

    /**
     * スレッド要約: 指定スレッドの全メールを参照して要約を生成
     *
     * オプションで provider / model をリクエスト本文から受け取り、
     * デフォルト設定をオーバーライドできる。
     */
    public function summarizeThread(Request $request, EmailThread $thread): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string|in:ollama,claude,gemini',
            'model'    => 'nullable|string|max:128',
            'skill'    => 'nullable|string|max:64',
            'prompt'   => 'nullable|string|max:8000',
        ]);

        $emails = $thread->emails()->with('attachments')->orderBy('received_at', 'asc')->get();
        if ($emails->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'このスレッドにはメールがありません。',
            ], 422);
        }

        // メール本文を時系列で連結 (各メール 800 文字まで)
        $threadContext = '';
        foreach ($emails as $i => $e) {
            $threadContext .= "[#" . ($i + 1) . "] ";
            $threadContext .= "From: " . ($e->from_label ?: $e->from_address ?: '不明') . "\n";
            $threadContext .= "To: "   . ($e->to_address ?: '—') . "\n";
            if ($e->cc) {
                $threadContext .= "Cc: " . $e->cc . "\n";
            }
            $threadContext .= "Date: " . ($e->received_at?->format('Y/m/d H:i') ?: '—') . "\n";
            $threadContext .= "Subject: " . ($e->subject ?: '(件名なし)') . "\n";
            $threadContext .= "Body:\n" . Str::limit((string) $e->plain_body, 800, '...(省略)') . "\n";
            if ($e->attachments && $e->attachments->count() > 0) {
                $names = $e->attachments->pluck('filename')->implode(', ');
                $threadContext .= "Attachments: " . $names . "\n";
            }
            $threadContext .= "----\n";
        }

        $aiSettings = AiSetting::getSettings();

        // スキル選択。リクエスト指定 > ユーザーの「AI要約デフォルト」> summarize > 先頭。
        $skills        = $this->skillService->getSkillsForUser(auth()->user());
        $skillKey      = $request->input('skill') ?: $this->skillService->getDefaultSummaryKey(auth()->user()) ?? 'summarize';
        $selectedSkill = $skills[$skillKey] ?? ($skills['summarize'] ?? array_values($skills)[0] ?? [
            'name'          => '要約',
            'system_prompt' => "あなたはサポート窓口担当者です。以下のメールスレッドを日本語で要約してください。\n出力フォーマット:\n1. 概要 (3〜5行で何のスレッドか)\n2. 経緯 (時系列で 5〜8 行の箇条書き)\n3. 未解決事項 / ネクストアクション (箇条書き、なければ「なし」)\n4. 重要な日付・金額・人物・固有名詞 (列挙)\n返信案や挨拶文は不要、要約のみ。",
        ]);

        // ユーザーの追加指示プロンプト
        $userPrompt = trim((string) $request->input('prompt', ''));

        // /コレクション 参照を抽出してプロンプトに注入
        $colRef = $this->expandCollectionReferences(
            $userPrompt,
            $selectedSkill['system_prompt'] ?? ''
        );

        $prompt  = "【システム指示】\n" . ($selectedSkill['system_prompt'] ?? '') . "\n\n";
        if (!empty($colRef['references_block'])) {
            $prompt .= $colRef['references_block'] . "\n";
        }
        $prompt .= "【スレッド件名】\n" . ($thread->subject ?: '(件名なし)') . "\n";
        if ($thread->ticket_number) {
            $prompt .= "【チケット番号】\n[#" . $thread->ticket_number . "]\n";
        }
        $prompt .= "【メール総数】" . $emails->count() . " 通\n\n";
        if ($userPrompt !== '') {
            $prompt .= "【ユーザーの追加指示】\n" . $userPrompt . "\n\n";
        }
        $prompt .= "【スレッド本文】\n" . $threadContext;

        // 優先度: リクエスト指定 > AiSetting デフォルト
        $provider = $request->input('provider') ?: $aiSettings->default_provider;
        $model    = $request->input('model')    ?: $aiSettings->default_model;

        // 非同期化: タスクを作って Job に投げ、task_id を返す
        $task = \App\Models\AiTask::create([
            'user_id'   => auth()->id(),
            'thread_id' => $thread->id,
            'task_type' => \App\Models\AiTask::TYPE_THREAD_SUMMARY,
            'status'    => \App\Models\AiTask::STATUS_PENDING,
            'provider'  => $provider,
            'model'     => $model,
            'prompt'    => $prompt,
            'result_meta' => [
                'skill_key'  => $skillKey,
                'skill_name' => $selectedSkill['name'] ?? $skillKey,
            ],
        ]);
        \App\Jobs\ProcessAiTask::dispatch($task->id);

        return response()->json([
            'status'      => 'pending',
            'task_id'     => $task->id,
            'email_count' => $emails->count(),
            'subject'     => $thread->subject,
            'ticket'      => $thread->ticket_number,
            'provider'    => $provider,
            'model'       => $model,
            'skill_key'   => $skillKey,
            'skill_name'  => $selectedSkill['name'] ?? $skillKey,
        ]);
    }

    // 返信予約 (TO/CC/BCC/添付対応)
    public function reply(Request $request, Email $email): JsonResponse
    {
        $validated = $request->validate([
            'from_address' => 'nullable|string|email',
            // 下書き保存 (save_as_draft=1) の場合は未入力でも保存可
            'to' => 'required_without:save_as_draft|nullable|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            // body = リッチエディタから出る plain テキスト (互換 / 検索用)
            // body_html = Quill が生成した HTML 本文 (送信時に multipart の HTML パートに使う)
            'body'      => 'required_without:save_as_draft|nullable|string',
            'body_html' => 'nullable|string',
            'created_by' => 'nullable|string',
            'approver_id' => 'nullable|integer|exists:users,id',
            'draft_id' => 'nullable|integer|exists:pending_emails,id',
            'attachments.*' => 'file|max:20480', // 20MB
            // 下書き編集時に保持する既存添付のパス (storePendingAttachments で保存されたもの)
            'keep_attachments'   => 'nullable|array',
            'keep_attachments.*' => 'string|max:512',
            // 予約送信の希望日時 (ヒント). 承認者が承認した時点で実際に予約状態へ遷移する.
            // 過去日時 / 空 は「即時送信を希望」とみなす.
            'scheduled_for' => 'nullable|date',
        ]);

        $saveAsDraft = $request->boolean('save_as_draft');
        // 既存下書きの再保存ケース (save_as_draft=1 かつ draft_id 指定 かつ本人の下書き) は
        // ID を維持して in-place update。これによって「保存して閉じる→再オープン」で
        // ID 変動による 404 が起きない。
        $reuseDraft = null;
        if ($saveAsDraft && !empty($validated['draft_id'])) {
            $candidate = PendingEmail::find($validated['draft_id']);
            if ($candidate
                && $candidate->status === PendingEmail::STATUS_DRAFT
                && $candidate->created_by_user_id === auth()->id()
            ) {
                $reuseDraft = $candidate;
            }
        }

        $pending = $reuseDraft ?: new PendingEmail();
        $pending->in_reply_to_email_id = $email->id;
        $pending->reply_type = PendingEmail::TYPE_REPLY;
        $pending->from_address = $validated['from_address'] ?? null;
        $pending->to_address = $validated['to'] ?? '';
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;
        // 件名にチケット番号タグが残っていれば剥がしてから "Re: " を付ける。
        // 送信件名にチケット番号は載せない方針。
        $cleanSubject = preg_replace(\App\Models\EmailThread::TICKET_REGEX, '', (string) $email->subject) ?? $email->subject;
        $cleanSubject = trim((string) $cleanSubject);
        $pending->subject = (str_starts_with($cleanSubject, 'Re:') || str_starts_with($cleanSubject, 're:'))
            ? $cleanSubject
            : "Re: " . $cleanSubject;
        $pending->body = $validated['body'] ?? '';
        // HTML 本文は表示用に簡易サニタイズしてから保存。送信時はこの値を multipart の HTML パートに使う。
        $pending->body_html = isset($validated['body_html']) && $validated['body_html'] !== ''
            ? \App\Models\Email::sanitizeHtml($validated['body_html'])
            : null;
        $pending->status = $saveAsDraft ? PendingEmail::STATUS_DRAFT : PendingEmail::STATUS_PENDING;
        $pending->created_by = $validated['created_by'] ?? (auth()->user()->name ?? '米住 直親');
        $pending->created_by_user_id = auth()->id();
        $pending->target_approver_user_id = $validated['approver_id'] ?? null;
        // 仕様変更 (2026-05): 予約送信は selfSend エンドポイントでのみ設定する.
        // compose/reply 経路は「下書き保存」または「承認依頼」用なので scheduled_for は常に null.
        // (承認依頼の送信時刻は承認者が決定する仕様)
        $pending->scheduled_for = null;

        // 既存添付 (下書き編集元) + 新規アップロードファイルを統合
        $pending->attachment_paths = $this->resolvePendingAttachments($request, $validated['draft_id'] ?? null);
        $pending->save();

        if (!$saveAsDraft) {
            $this->notifyAdmins($pending);
            // 承認依頼が発生したスレッドは inbox から「承認待ち」へ移動
            PendingEmail::syncThreadStatus($email->thread_id);
        }

        // in-place update したケースでは元の下書きは削除しない (=同一 ID として再利用済み)
        if (!$reuseDraft) {
            $this->deleteSourceDraftIfAny($validated['draft_id'] ?? null);
        }

        return response()->json(['status' => 'ok', 'id' => $pending->id]);
    }

    /**
     * 転送 (Forward) を承認依頼 / 下書き保存する.
     *
     * reply() と非常に近いが、以下が異なる:
     *   - reply_type = TYPE_FORWARD
     *   - subject は frontend から受け取る (= ユーザが "Fwd: " プレフィックスを編集できる).
     *     reply は強制的に "Re: " を付け直すが、forward は自由編集.
     *   - inherit_attachment_ids[] = 元メールの EmailAttachment ID 配列. 受信時にファイル実体を
     *     コピーして pending の attachment_paths に追加する. UI でチェックを外したものは含まれない.
     */
    public function forward(Request $request, Email $email): JsonResponse
    {
        $validated = $request->validate([
            'from_address' => 'nullable|string|email',
            'to' => 'required_without:save_as_draft|nullable|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'subject' => 'required_without:save_as_draft|nullable|string|max:512',
            'body'      => 'required_without:save_as_draft|nullable|string',
            'body_html' => 'nullable|string',
            'created_by' => 'nullable|string',
            'approver_id' => 'nullable|integer|exists:users,id',
            'draft_id' => 'nullable|integer|exists:pending_emails,id',
            'attachments.*' => 'file|max:20480',
            'keep_attachments'   => 'nullable|array',
            'keep_attachments.*' => 'string|max:512',
            // 元メールから引き継ぐ EmailAttachment ID 配列. integer に validate.
            'inherit_attachment_ids'   => 'nullable|array',
            'inherit_attachment_ids.*' => 'integer',
            'scheduled_for' => 'nullable|date',
        ]);

        $saveAsDraft = $request->boolean('save_as_draft');

        // 既存下書きの再保存ケース.
        $reuseDraft = null;
        if ($saveAsDraft && !empty($validated['draft_id'])) {
            $candidate = PendingEmail::find($validated['draft_id']);
            if ($candidate
                && $candidate->status === PendingEmail::STATUS_DRAFT
                && $candidate->created_by_user_id === auth()->id()
            ) {
                $reuseDraft = $candidate;
            }
        }

        $pending = $reuseDraft ?: new PendingEmail();
        $pending->in_reply_to_email_id = $email->id;
        $pending->reply_type = PendingEmail::TYPE_FORWARD;
        $pending->from_address = $validated['from_address'] ?? null;
        $pending->to_address = $validated['to'] ?? '';
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;

        // 件名は frontend から受け取った値をそのまま採用 (ユーザが Fwd: を編集可能).
        // ただし、安全策としてチケット番号タグが入っていれば剥がす + 空文字なら "Fwd: " を補う.
        $subj = (string) ($validated['subject'] ?? '');
        $subj = preg_replace(\App\Models\EmailThread::TICKET_REGEX, '', $subj) ?? $subj;
        $subj = trim($subj);
        if ($subj === '') {
            $orig = trim(preg_replace(\App\Models\EmailThread::TICKET_REGEX, '', (string) $email->subject) ?? (string) $email->subject);
            $subj = 'Fwd: ' . $orig;
        }
        $pending->subject = mb_substr($subj, 0, 500);

        $pending->body = $validated['body'] ?? '';
        $pending->body_html = isset($validated['body_html']) && $validated['body_html'] !== ''
            ? \App\Models\Email::sanitizeHtml($validated['body_html'])
            : null;
        $pending->status = $saveAsDraft ? PendingEmail::STATUS_DRAFT : PendingEmail::STATUS_PENDING;
        $pending->created_by = $validated['created_by'] ?? (auth()->user()->name ?? '');
        $pending->created_by_user_id = auth()->id();
        $pending->target_approver_user_id = $validated['approver_id'] ?? null;
        // 転送は reply と同じく selfSend 経路で予約日時を扱う (= ここでは常に null).
        $pending->scheduled_for = null;

        // 添付ファイルの統合:
        //   1) ドラフト編集経由なら既存添付 (keep_attachments)
        //   2) 新規アップロード
        //   3) 元メールから継承する EmailAttachment (= ファイル実体をコピー)
        $base = $this->resolvePendingAttachments($request, $validated['draft_id'] ?? null);
        $inheritIds = $validated['inherit_attachment_ids'] ?? [];
        if (!empty($inheritIds)) {
            foreach ($this->cloneEmailAttachmentsForForward($email, $inheritIds) as $att) {
                $base[] = $att;
            }
        }
        $pending->attachment_paths = $base;
        $pending->save();

        if (!$saveAsDraft) {
            $this->notifyAdmins($pending);
            // 転送はそれ自体が新しい話なので「承認待ち」化は元スレッドではなく転送自身.
            // 既存スレッドの status は触らない (ユーザ要望: 転送で元スレッドの状態を変えない).
        }

        if (!$reuseDraft) {
            $this->deleteSourceDraftIfAny($validated['draft_id'] ?? null);
        }

        return response()->json(['status' => 'ok', 'id' => $pending->id]);
    }

    /**
     * 転送時、元メールの添付 (EmailAttachment) からファイル実体を pending ストレージにコピーして
     * pending->attachment_paths と同形式の配列で返す.
     *
     *   - email_attachments.disk_path は private ディスクの 'attachments/{email_id}/...' 配下に保存されている.
     *   - 新規パスは 'attachments/pending/' 配下に一意名で書き込む.
     *   - ファイル取得 / 書き込み失敗は個別に warning ログ + スキップ (転送自体は止めない).
     *
     * セキュリティ: 自分が所属するスレッドの添付しか引き継げないようにする必要があるが、
     * 当面は EmailAttachment の email_id == $email->id を満たすものだけ取り出すことでガード.
     */
    private function cloneEmailAttachmentsForForward(Email $email, array $ids): array
    {
        if (empty($ids)) return [];
        $atts = \App\Models\EmailAttachment::whereIn('id', $ids)
            ->where('email_id', $email->id)
            ->get();
        $out = [];
        foreach ($atts as $a) {
            $src = (string) $a->disk_path;
            if ($src === '') continue;
            try {
                if (!Storage::disk('private')->exists($src)) {
                    \Illuminate\Support\Facades\Log::warning('forward attachment: source file missing', [
                        'attachment_id' => $a->id, 'disk_path' => $src,
                    ]);
                    continue;
                }
                $bytes = Storage::disk('private')->get($src);
                // 一意な保存パスを生成 (元のファイル名拡張子を維持).
                $baseName = basename((string) $a->filename) ?: ('att_' . $a->id);
                $newPath  = 'attachments/pending/' . uniqid('fwd_', true) . '_' . $baseName;
                Storage::disk('private')->put($newPath, $bytes);
                $out[] = [
                    'path'      => $newPath,
                    'filename'  => (string) $a->filename,
                    'mime_type' => (string) ($a->mime_type ?: 'application/octet-stream'),
                    'size'      => (int) ($a->size ?? mb_strlen($bytes, '8bit')),
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('forward attachment clone failed', [
                    'attachment_id' => $a->id, 'error' => $e->getMessage(),
                ]);
            }
        }
        return $out;
    }

    // 新規作成予約 (承認待ち)
    public function compose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_address' => 'nullable|string|email',
            // 下書き保存 (save_as_draft=1) の場合は未入力でも保存可
            'to' => 'required_without:save_as_draft|nullable|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            // body = plain テキスト, body_html = リッチエディタの HTML
            'body'      => 'required_without:save_as_draft|nullable|string',
            'body_html' => 'nullable|string',
            'subject' => 'nullable|string',
            'created_by' => 'nullable|string',
            'approver_id' => 'nullable|integer|exists:users,id',
            'draft_id' => 'nullable|integer|exists:pending_emails,id',
            'attachments.*' => 'file|max:20480',
            // 下書き編集時に保持する既存添付のパス
            'keep_attachments'   => 'nullable|array',
            'keep_attachments.*' => 'string|max:512',
            // 予約送信の希望日時 (ヒント). 承認時に判定 (上の reply() と同じ仕様).
            'scheduled_for' => 'nullable|date',
        ]);

        $saveAsDraft = $request->boolean('save_as_draft');
        // 既存下書きの再保存ケース (save_as_draft=1 かつ draft_id 指定 かつ本人の下書き) は
        // ID を維持して in-place update。
        $reuseDraft = null;
        if ($saveAsDraft && !empty($validated['draft_id'])) {
            $candidate = PendingEmail::find($validated['draft_id']);
            if ($candidate
                && $candidate->status === PendingEmail::STATUS_DRAFT
                && $candidate->created_by_user_id === auth()->id()
            ) {
                $reuseDraft = $candidate;
            }
        }

        $pending = $reuseDraft ?: new PendingEmail();
        $pending->reply_type = PendingEmail::TYPE_COMPOSE;
        $pending->from_address = $validated['from_address'] ?? null;
        $pending->to_address = $validated['to'] ?? '';
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;
        $pending->subject = $validated['subject'] ?: '(無題)';
        $pending->body = $validated['body'] ?? '';
        $pending->body_html = isset($validated['body_html']) && $validated['body_html'] !== ''
            ? \App\Models\Email::sanitizeHtml($validated['body_html'])
            : null;
        $pending->status = $saveAsDraft ? PendingEmail::STATUS_DRAFT : PendingEmail::STATUS_PENDING;
        $pending->created_by = $validated['created_by'] ?? (auth()->user()->name ?? '米住 直親');
        $pending->created_by_user_id = auth()->id();
        $pending->target_approver_user_id = $validated['approver_id'] ?? null;
        // 仕様変更 (2026-05): 予約送信は selfSend エンドポイントでのみ設定する.
        // compose/reply 経路は「下書き保存」または「承認依頼」用なので scheduled_for は常に null.
        $pending->scheduled_for = null;

        // 既存添付 (下書き編集元) + 新規アップロードファイルを統合
        $pending->attachment_paths = $this->resolvePendingAttachments($request, $validated['draft_id'] ?? null);
        $pending->save();

        if (!$saveAsDraft) {
            $this->notifyAdmins($pending);
        }

        // in-place update したケースでは元の下書きは削除しない (=同一 ID として再利用済み)
        if (!$reuseDraft) {
            $this->deleteSourceDraftIfAny($validated['draft_id'] ?? null);
        }

        return response()->json(['status' => 'ok', 'id' => $pending->id]);
    }

    /**
     * compose/reply の draft_id 指定で元の下書きを削除する (本人 + draft 状態のみ)
     */
    private function deleteSourceDraftIfAny(?int $draftId): void
    {
        if (!$draftId) return;
        $draft = PendingEmail::find($draftId);
        if (!$draft) return;
        if ($draft->status !== PendingEmail::STATUS_DRAFT) return;
        if ($draft->created_by_user_id !== auth()->id()) return;

        // 却下からの再生成下書きの場合、元の却下済レコードも合わせて削除し、
        // 却下済一覧に重複したレコードが残らないようにする。
        $sourceRejectedId = $draft->source_rejected_id ?? null;
        if ($sourceRejectedId) {
            $sourceRejected = PendingEmail::find($sourceRejectedId);
            if ($sourceRejected
                && $sourceRejected->status === PendingEmail::STATUS_REJECTED
                && $sourceRejected->created_by_user_id === auth()->id()
            ) {
                $sourceRejected->delete();
            }
        }

        $draft->delete();
    }

    /**
     * 下書き編集時の既存添付 (draft_id 指定) + 新規アップロードを統合し、
     * 新 pending_email の attachment_paths として使う配列を返す。
     *
     * - $draftId が指定されている場合、その下書きの既存添付のうち
     *   keep_attachments[] に含まれるパスだけを新 pending に引き継ぐ。
     *   UI 側で「削除」されたものはこのリストから外れているため、ストレージからも削除。
     * - 新規アップロードファイルは storePendingAttachments で保存して結合。
     */
    private function resolvePendingAttachments(Request $request, ?int $draftId): array
    {
        $merged = [];

        // (1) 既存添付の引き継ぎ
        if ($draftId) {
            $sourceDraft = PendingEmail::find($draftId);
            if ($sourceDraft
                && $sourceDraft->status === PendingEmail::STATUS_DRAFT
                && $sourceDraft->created_by_user_id === auth()->id()
                && is_array($sourceDraft->attachment_paths)
            ) {
                $keep = array_filter((array) $request->input('keep_attachments', []), fn($v) => is_string($v) && $v !== '');
                foreach ($sourceDraft->attachment_paths as $att) {
                    $info = $this->normalizeAttachment($att);
                    if (!$info) continue;
                    if (!in_array($info['path'], $keep, true)) {
                        // UI で削除された (keep に含まれない) 添付はストレージからも削除
                        try { Storage::disk('private')->delete($info['path']); } catch (\Throwable $e) { /* noop */ }
                        continue;
                    }
                    $merged[] = $info;
                }
            }
        }

        // (2) 新規アップロード
        foreach ($this->storePendingAttachments($request) as $att) {
            $merged[] = $att;
        }

        return $merged;
    }

    /**
     * attachment_paths の各要素を {path, filename, mime_type, size} 形式に整える。
     * 旧形式 (path のみの文字列等) にも耐性を持たせる。
     */
    private function normalizeAttachment($att): ?array
    {
        if (is_string($att)) {
            return [
                'path'      => $att,
                'filename'  => basename($att),
                'mime_type' => 'application/octet-stream',
                'size'      => 0,
            ];
        }
        if (is_array($att) && isset($att['path'])) {
            return [
                'path'      => (string) $att['path'],
                'filename'  => (string) ($att['filename'] ?? basename($att['path'])),
                'mime_type' => (string) ($att['mime_type'] ?? 'application/octet-stream'),
                'size'      => (int) ($att['size'] ?? 0),
            ];
        }
        return null;
    }

    /**
     * 添付ファイルを保存し、{path, filename, mime_type, size} の配列で返す。
     */
    private function storePendingAttachments(Request $request): array
    {
        if (!$request->hasFile('attachments')) {
            return [];
        }
        $records = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store('attachments/pending', 'private');
            if (!$path) {
                continue;
            }
            $records[] = [
                'path'      => $path,
                'filename'  => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size'      => (int) $file->getSize(),
            ];
        }
        return $records;
    }

    private function notifyAdmins(PendingEmail $pending): void
    {
        $admins = User::where('role', 'admin')
            ->where('id', '!=', auth()->id())
            ->get();

        Notification::send($admins, new ApprovalRequestedNotification($pending));
    }

    /**
     * 現在のフィルタ (room / customer / assignee / search 等) を適用したうえでの
     * ステータス別件数を返す軽量エンドポイント.
     *
     * フロントは「対応不要・承認待ち・迷惑メール」等の補助タブを件数 > 0 の時だけ表示する
     * ために使う. クエリは search と全く同じ条件を受け取り、status と allStatus 以外を共有する.
     */
    public function statusCounts(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $cid = $request->input('customer_id');
        $gid = $request->input('group_id');
        $assigneeId = $request->input('assigned_user_id');
        $chatRoomId = $request->input('chat_room_id');
        $isPinned = $request->boolean('is_pinned');

        $base = EmailThread::query()
            ->whereNotIn('id', \App\Models\ThreadMerge::select('source_thread_id_original'))
            ->where(function ($q) {
                $q->where('is_manual_upload', false)->orWhereNull('is_manual_upload');
            })
            ->has('emails');

        if ($query !== '') {
            $base->where(function ($q) use ($query) {
                $q->where('subject', 'like', "%{$query}%")
                  ->orWhereHas('emails', fn($e) => $e->where('plain_body', 'like', "%{$query}%"));
            });
        }
        if ($cid === 'none') $base->whereNull('customer_id');
        elseif ($cid)        $base->where('customer_id', $cid);
        if ($gid)            $base->whereHas('customer', fn($q) => $q->where('group_id', $gid));
        if ($assigneeId === 'none') $base->whereNull('assigned_user_id');
        elseif ($assigneeId && $assigneeId !== 'all') $base->where('assigned_user_id', $assigneeId);
        // ピン留めはユーザ毎 (UserChatPin) なので、ここでは自分がピン留めしたスレッドのみに絞る.
        if ($isPinned) {
            try {
                $pinnedIds = \App\Models\UserChatPin::where('user_id', auth()->id())
                    ->where('pinnable_type', \App\Models\UserChatPin::TYPE_THREAD)
                    ->pluck('pinnable_id')->all();
                if (empty($pinnedIds)) { $base->whereRaw('1 = 0'); }
                else                   { $base->whereIn('id', $pinnedIds); }
            } catch (\Throwable) { $base->whereRaw('1 = 0'); }
        }

        if ($chatRoomId === 'none' || $chatRoomId === '__none__') {
            $base->whereNotIn('id', \Illuminate\Support\Facades\DB::table('chat_room_thread')
                ->select('email_thread_id'));
        } elseif ($chatRoomId && $chatRoomId !== 'all') {
            $room = \App\Models\ChatRoom::visibleTo(auth()->id())->find($chatRoomId);
            if ($room) {
                $roomIds = $room->descendantRoomIds();
                $bundledIds = \Illuminate\Support\Facades\DB::table('chat_room_thread')
                    ->whereIn('chat_room_id', $roomIds)
                    ->pluck('email_thread_id')
                    ->map(fn($i) => (int) $i)->unique()->values()->all();
                $base->whereIn('id', $bundledIds ?: [0]);
            } else {
                $base->whereRaw('1 = 0');
            }
        }

        // 各ステータスごとに count() を 1 クエリで取得 (groupBy で 6 通り分まとめて).
        $rows = (clone $base)
            ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();
        return response()->json([
            'inbox'     => (int) ($rows['inbox']     ?? 0),
            'hold'      => (int) ($rows['hold']      ?? 0),
            'completed' => (int) ($rows['completed'] ?? 0),
            'no_action' => (int) ($rows['no_action'] ?? 0),
            'pending'   => (int) ($rows['pending']   ?? 0),
            'spam'      => (int) ($rows['spam']      ?? 0),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $status = $request->query('status');
        $cid = $request->input('customer_id');
        $gid = $request->input('group_id');
        $allStatus = $request->boolean('all_status');
        $isPinned = $request->boolean('is_pinned');
        $assigneeId = $request->input('assigned_user_id');
        $sortKey = $request->input('sort_key', 'last_email_at');
        $sortOrder = $request->input('sort_order', 'desc');
        // ルームフィルター: 指定 chat_room に紐付けられたスレッドのみ表示
        $chatRoomId = $request->input('chat_room_id');

        $threads = EmailThread::with('latestEmail', 'customer', 'assignee')->withCount('threadMerges')
            ->whereNotIn('id', \App\Models\ThreadMerge::select('source_thread_id_original'))
            // 添付ファイル管理画面の「アップロード」で作られた合成スレッドは
            // メール一覧からは除外する (添付一覧 / ルームのバンドル先には引き続き出す)
            ->where(function ($q) {
                $q->where('is_manual_upload', false)
                  ->orWhereNull('is_manual_upload');
            })
            // 孤児スレッド (Email 行を 1 通も持たない) を除外する。
            // 旧 EmailFetcher で「スレッド先行作成 → Email::create 失敗」の経路で
            // 残ってしまった残骸が「不明な送信者」として一覧に出ていたため。
            // 新しい取り込みは DB::transaction で囲まれているので新規発生はしないが、
            // 既存データの可視性を守るためにここで filter を挟む。
            ->has('emails')
            ->orderBy($sortKey, $sortOrder);

        // メール一覧 (中央カラム) は user_chat_hides の影響を受けない (ユーザー指示)。
        // 非表示はサイドバーのスレッド・添付・チャットだけが対象。

        // ルームフィルター。値の解釈:
        //   - 'all'  または空 → フィルタなし (全スレッド)
        //   - 'none'        → 「どのルームにも属していない」スレッドだけ
        //   - 数値 ID       → 指定ルームでバンドルされたスレッドだけ
        if ($chatRoomId === 'none' || $chatRoomId === '__none__') {
            // 「ルーム未設定」: chat_room_thread ピボットに 1 行も無いスレッド
            // (= どのルームにも紐付いていない = 整理されていないスレッドを掘り起こすため)
            $threads->whereNotIn('id', \Illuminate\Support\Facades\DB::table('chat_room_thread')
                ->select('email_thread_id'));
        } elseif ($chatRoomId) {
            $room = \App\Models\ChatRoom::visibleTo(auth()->id())->find($chatRoomId);
            if ($room) {
                // 階層対応: 自身 + 全子孫ルームのバンドルスレッドを集める.
                // (親ルームを開いたとき子孫ルームのスレッドも一緒に出すため)
                $roomIds = $room->descendantRoomIds();
                $bundledIds = \Illuminate\Support\Facades\DB::table('chat_room_thread')
                    ->whereIn('chat_room_id', $roomIds)
                    ->pluck('email_thread_id')
                    ->map(fn($i) => (int) $i)
                    ->unique()
                    ->values()
                    ->all();
                $threads->whereIn('id', $bundledIds ?: [0]); // 空なら必ず空の結果
            } else {
                $threads->whereRaw('1 = 0'); // 不可視・存在しないルームは空結果
            }
        }

        // 全表示トグルがOFFの場合のみステータスフィルタを適用
        if (!$allStatus && $status) {
            $threads->where('status', $status);
        }

        // spam は専用タブで明示指定された時 (status=spam) だけ表示。
        // それ以外 (受信/保留/完了/承認待ち/全表示) のすべての一覧から spam は除外する。
        if ($status !== EmailThread::STATUS_SPAM) {
            $threads->where(function ($q) {
                $q->where('status', '!=', EmailThread::STATUS_SPAM)
                  ->orWhereNull('status');
            });
        }

        // ゴミ箱は専用ビュー (/trash) でのみ表示する.
        // status='trash' OR trashed_at IS NOT NULL のスレッドは全ての通常一覧から除外する.
        // (trash 専用 status を引きにきた場合のみ別経路 = trashIndex を使う前提)
        if ($status !== EmailThread::STATUS_TRASH) {
            $threads->where(function ($q) {
                $q->where('status', '!=', EmailThread::STATUS_TRASH)
                  ->orWhereNull('status');
            })->whereNull('trashed_at');
        }

        // ピン留めはユーザ毎に管理 (UserChatPin, pinnable_type='thread').
        // 旧実装は email_threads.is_pinned (グローバル) を使っていたが、他ユーザの操作が
        // 自分の画面に反映してしまう問題があったため per-user に変更.
        $userPinnedThreadIds = [];
        try {
            $userPinnedThreadIds = \App\Models\UserChatPin::where('user_id', auth()->id())
                ->where('pinnable_type', \App\Models\UserChatPin::TYPE_THREAD)
                ->pluck('pinnable_id')
                ->map(fn($x) => (int) $x)
                ->all();
        } catch (\Throwable) {}
        $userPinnedSet = array_flip($userPinnedThreadIds); // O(1) lookup

        if ($isPinned) {
            // 自分がピン留めしたスレッドだけに絞り込む.
            if (empty($userPinnedThreadIds)) {
                // ピン留め無しの状態でこの条件が来たら空配列で短絡しておく.
                $threads->whereRaw('1 = 0');
            } else {
                $threads->whereIn('id', $userPinnedThreadIds);
            }
        }

        if ($assigneeId) {
            if ($assigneeId === 'none') $threads->whereNull('assigned_user_id');
            else $threads->where('assigned_user_id', $assigneeId);
        }

        if ($query) {
            $threads->where(function($q) use ($query) {
                $q->where('subject', 'like', "%{$query}%")
                  ->orWhereHas('emails', fn($e) => $e->where('body_text', 'like', "%{$query}%"));
            });
        }
        if ($cid) {
            if ($cid === 'none') $threads->whereNull('customer_id');
            else $threads->where('customer_id', $cid);
        }
        if ($gid) {
            if ($gid === 'none') $threads->whereHas('customer', fn($c) => $c->whereNull('group_id'));
            else $threads->whereHas('customer', fn($c) => $c->where('group_id', $gid));
        }
        $loadedThreads = $threads->get();

        // メール一覧で「すべてのルーム」表示時にバンドル先ルームをラベル表示するため、
        // thread → 所属ルーム一覧 の逆引きマップを作る。
        // 個人ルームはログインユーザーが作成者の場合だけ含める (他人の個人ルームを漏らさない)。
        $roomsByThread = [];
        try {
            $tIds = $loadedThreads->pluck('id')->all();
            if (!empty($tIds)) {
                $authId = auth()->id();
                $pivotRows = \Illuminate\Support\Facades\DB::table('chat_room_thread')
                    ->join('chat_rooms', 'chat_rooms.id', '=', 'chat_room_thread.chat_room_id')
                    ->whereIn('chat_room_thread.email_thread_id', $tIds)
                    ->where(function ($q) use ($authId) {
                        $q->where('chat_rooms.is_private', false);
                        if ($authId !== null) {
                            $q->orWhere(function ($qq) use ($authId) {
                                $qq->where('chat_rooms.is_private', true)
                                   ->where('chat_rooms.created_by_user_id', $authId);
                            });
                        }
                    })
                    ->get([
                        'chat_room_thread.email_thread_id',
                        'chat_rooms.id',
                        'chat_rooms.name',
                        'chat_rooms.is_private',
                    ]);
                foreach ($pivotRows as $r) {
                    $tid = (int) $r->email_thread_id;
                    $roomsByThread[$tid] ??= [];
                    $roomsByThread[$tid][] = [
                        'id'         => (int) $r->id,
                        'name'       => (string) $r->name,
                        'is_private' => (bool) $r->is_private,
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('EmailController.search: failed to load rooms map: ' . $e->getMessage());
        }

        // 未読チャット件数 + チャット最終投稿時刻を thread ごとに集計
        $unreadByThread     = [];
        $lastChatAtByThread = [];
        try {
            $tIds = $loadedThreads->pluck('id')->all();
            $myId = auth()->id();
            if (!empty($tIds)) {
                // 最終チャット投稿時刻 (誰の投稿でも対象 / ソート用)
                $maxRows = \App\Models\ThreadComment::whereIn('thread_id', $tIds)
                    ->selectRaw('thread_id, MAX(created_at) as max_at')
                    ->groupBy('thread_id')
                    ->get();
                foreach ($maxRows as $r) {
                    $lastChatAtByThread[$r->thread_id] = $r->max_at;
                }

                if ($myId) {
                    $reads = \App\Models\UserThreadChatRead::where('user_id', $myId)
                        ->whereIn('thread_id', $tIds)
                        ->pluck('last_read_at', 'thread_id')
                        ->all();

                    // thread ごとに「自分以外の」コメントで last_read_at より新しいものを数える
                    $rows = \App\Models\ThreadComment::whereIn('thread_id', $tIds)
                        ->where('user_id', '!=', $myId)
                        ->get(['id', 'thread_id', 'user_id', 'created_at']);
                    foreach ($rows as $c) {
                        $lastRead = $reads[$c->thread_id] ?? null;
                        if ($lastRead === null || $c->created_at > $lastRead) {
                            $unreadByThread[$c->thread_id] = ($unreadByThread[$c->thread_id] ?? 0) + 1;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // テーブル未作成等で壊れないように
        }

        $result = $loadedThreads->map(function ($t) use ($unreadByThread, $lastChatAtByThread, $roomsByThread, $userPinnedSet) {
            $lastChatAt = $lastChatAtByThread[$t->id] ?? null;
            // ソート用キー: ピン留めが最上位、その下は (チャット最終 OR メール最終) の新しい順
            $emailIso = $t->last_email_at?->toIso8601String() ?? '';
            $chatIso  = $lastChatAt ? \Carbon\Carbon::parse($lastChatAt)->toIso8601String() : '';
            $activity = $chatIso > $emailIso ? $chatIso : $emailIso;

            return [
                'id' => $t->id,
                'subject' => $t->subject,
                'ticket_number' => $t->ticket_number,
                'status' => $t->status,
                // is_pinned はユーザ毎の UserChatPin から判定 (旧 email_threads.is_pinned は無視).
                'is_pinned' => isset($userPinnedSet[(int) $t->id]),
                'assigned_user_id' => $t->assigned_user_id,
                'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
                'last_email_at' => $t->last_email_at?->format('Y/m/d H:i'),
                'last_chat_at' => $lastChatAt ? \Carbon\Carbon::parse($lastChatAt)->format('Y/m/d H:i') : null,
                'last_activity_iso' => $activity,
                'customer_id' => $t->customer_id,
                'customer' => $t->customer ? ['name' => $t->customer->name] : null,
                'latest_email' => $t->latestEmail ? [
                    'from_label'   => $t->latestEmail->from_label,
                    'from_address' => $t->latestEmail->from_address,
                    // クイックフィル用に To / Cc / Bcc も同梱.
                    // ルーム振り分けルールの引用チップで使う. 数値文字列レベルの payload なので無視できる.
                    'to_address'   => $t->latestEmail->to_address,
                    'cc'           => $t->latestEmail->cc,
                    'bcc'          => $t->latestEmail->bcc,
                    'plain_body'   => Str::limit($t->latestEmail->plain_body, 50),
                ] : null,
                'unread_chat_count' => (int) ($unreadByThread[$t->id] ?? 0),
                // バンドル先ルーム一覧 (「すべてのルーム」表示時にチップ表示用)。
                // 個人ルームは「自分が作成者」のものだけが入る。
                'bundled_rooms' => $roomsByThread[$t->id] ?? [],
            ];
        })
        // ピン留めが最上位 → ユーザ指定の sort_order に従って最新活動順に並べる。
        // (以前は常に desc にしていたため、画面の昇順/降順トグルが効かなかった)
        ->sort(function ($a, $b) use ($sortOrder) {
            if ($a['is_pinned'] !== $b['is_pinned']) {
                return $a['is_pinned'] ? -1 : 1;
            }
            return $sortOrder === 'asc'
                ? strcmp($a['last_activity_iso'], $b['last_activity_iso'])
                : strcmp($b['last_activity_iso'], $a['last_activity_iso']);
        })
        ->values();

        return response()->json($result);
    }

    /**
     * スレッドのピン留めを「自分だけ」に対してトグルする (per-user).
     *
     * 仕様: UserChatPin (user_id, pinnable_type='thread', pinnable_id) で個別に記録.
     *       email_threads.is_pinned は触らない (旧データ互換のため列は残置).
     *       自分のピン留めが他ユーザに影響しないようにすることが目的.
     */
    public function togglePin(Request $request, EmailThread $thread): JsonResponse
    {
        $userId = auth()->id();
        $existing = \App\Models\UserChatPin::where('user_id', $userId)
            ->where('pinnable_type', \App\Models\UserChatPin::TYPE_THREAD)
            ->where('pinnable_id', $thread->id)
            ->first();

        // is_pinned パラメータが明示されている場合はその値に従う. 無ければトグル.
        $wantPinned = $request->has('is_pinned')
            ? $request->boolean('is_pinned')
            : !$existing;

        if ($wantPinned && !$existing) {
            \App\Models\UserChatPin::create([
                'user_id'       => $userId,
                'pinnable_type' => \App\Models\UserChatPin::TYPE_THREAD,
                'pinnable_id'   => $thread->id,
            ]);
        } elseif (!$wantPinned && $existing) {
            $existing->delete();
        }

        return response()->json(['status' => 'ok', 'is_pinned' => $wantPinned]);
    }

    public function updateAssignee(Request $request, EmailThread $thread): JsonResponse
    {
        $thread->update(['assigned_user_id' => $request->input('assigned_user_id')]);
        return response()->json(['status' => 'ok']);
    }

    public function users(): JsonResponse
    {
        $users = \App\Models\User::orderBy('name')->get(['id', 'name', 'email']);
        return response()->json($users);
    }

    public function thread(EmailThread $thread): JsonResponse
    {
        $thread->load(['customer', 'assignee']);

        $threadIds = [$thread->id];
        $mergedSourceIds = \App\Models\ThreadMerge::where('target_thread_id', $thread->id)->pluck('source_thread_id_original')->toArray();
        $threadIds = array_merge($threadIds, $mergedSourceIds);

        // ピン留めはユーザ毎 (UserChatPin). スレッド表示時の thread_is_pinned もユーザ視点で判定.
        $myPinnedThreads = [];
        try {
            $myPinnedThreads = array_flip(\App\Models\UserChatPin::where('user_id', auth()->id())
                ->where('pinnable_type', \App\Models\UserChatPin::TYPE_THREAD)
                ->whereIn('pinnable_id', $threadIds)
                ->pluck('pinnable_id')->map(fn($x) => (int) $x)->all());
        } catch (\Throwable) {}

        // ゴミ箱化された個別メールはスレッド詳細ビューからは隠す (一覧と整合).
        // 復元する場合は /trash 画面の「復元」ボタンから操作する.
        $emails = \App\Models\Email::whereIn('thread_id', $threadIds)
            ->whereNull('trashed_at')
            ->with('attachments', 'thread')->orderBy('received_at', 'asc')->get()->map(fn($e) => [
            'id' => $e->id,
            'thread_id' => $e->thread_id,
            'thread_status' => $e->thread->status ?? 'inbox',
            'thread_is_pinned' => isset($myPinnedThreads[(int) ($e->thread->id ?? -1)]),
            'subject' => $e->subject,
            'from_label' => $e->from_label,
            'from_address' => $e->from_address,
            'to_address' => $e->to_address,
            'cc' => $e->cc,
            'body_html' => $e->body_html,
            // XSS サニタイズ済み HTML (iframe srcdoc 用)
            'safe_body_html' => $e->safe_body_html,
            'plain_body' => $e->plain_body,
            'received_at' => $e->received_at?->format('Y/m/d H:i'),
            'attachments' => $e->attachments->map(fn($a) => [
                'id' => $a->id,
                'filename' => $a->filename,
                'url' => route('attachments.download', $a->id),
            ])->values(),
        ]);

        $merges = $thread->threadMerges()->get()->map(fn($m) => [
            'id' => $m->id,
            'source_subject' => $m->source_subject,
            'created_at' => $m->created_at?->format('Y/m/d H:i'),
        ]);

        $emailIds = \App\Models\Email::whereIn('thread_id', $threadIds)->pluck('id');
        $pendingApprovals = PendingEmail::whereIn('in_reply_to_email_id', $emailIds)
            ->whereIn('status', [PendingEmail::STATUS_PENDING, PendingEmail::STATUS_APPROVED])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'status', 'subject', 'created_at']);

        // このスレッドがバンドルされているルーム一覧をスレッド詳細ヘッダで表示する。
        // - 共有/個人で分けて並べる
        // - 個人ルームはログインユーザーが作成者のものだけ含める (他人の個人ルームは漏らさない)
        $bundledRooms = ['shared' => [], 'private' => []];
        try {
            $authId = auth()->id();
            $rows = \Illuminate\Support\Facades\DB::table('chat_room_thread')
                ->join('chat_rooms', 'chat_rooms.id', '=', 'chat_room_thread.chat_room_id')
                ->where('chat_room_thread.email_thread_id', $thread->id)
                ->where(function ($q) use ($authId) {
                    $q->where('chat_rooms.is_private', false);
                    if ($authId !== null) {
                        $q->orWhere(function ($qq) use ($authId) {
                            $qq->where('chat_rooms.is_private', true)
                               ->where('chat_rooms.created_by_user_id', $authId);
                        });
                    }
                })
                ->orderBy('chat_rooms.name')
                ->get([
                    'chat_rooms.id', 'chat_rooms.name', 'chat_rooms.is_private',
                    // 監査列: どのルールでマッチしてこのルームに入ったか
                    'chat_room_thread.matched_rule_type',
                    'chat_room_thread.matched_rule_pattern',
                    'chat_room_thread.matched_at',
                ]);
            $typeLabels = [
                'from_address'     => '差出人',
                'from_domain'      => 'ドメイン (From)',
                'subject_contains' => '件名',
                'to_contains'      => '宛先',
                'any_address'      => 'アドレス',
                'any_domain'       => 'ドメイン',
            ];
            foreach ($rows as $r) {
                $entry = [
                    'id'         => (int) $r->id,
                    'name'       => (string) $r->name,
                    'is_private' => (bool) $r->is_private,
                    // ルールマッチ情報 (NULL = 手動追加)
                    'matched_rule_type'    => $r->matched_rule_type,
                    'matched_rule_label'   => $r->matched_rule_type ? ($typeLabels[$r->matched_rule_type] ?? $r->matched_rule_type) : null,
                    'matched_rule_pattern' => $r->matched_rule_pattern,
                    'matched_at'           => $r->matched_at ? \Carbon\Carbon::parse($r->matched_at)->format('Y/m/d H:i') : null,
                ];
                if ($entry['is_private']) {
                    $bundledRooms['private'][] = $entry;
                } else {
                    $bundledRooms['shared'][] = $entry;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('EmailController.thread: failed to load bundled rooms: ' . $e->getMessage());
        }

        return response()->json([
            'thread'            => $thread,
            'emails'            => $emails,
            'merges'            => $merges,
            'pending_approvals' => $pendingApprovals,
            'bundled_rooms'     => $bundledRooms,
        ]);
    }

    public function updateStatus(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:inbox,hold,completed,no_action,pending,spam',
        ]);

        // status='spam' に切り替わる時は spammed_at = now() を新規セット (= 保持期間の起点).
        // status が spam から非 spam に戻る時は spammed_at をクリアして purge 対象から外す.
        // status='trash' は別エンドポイント (deleteThread) からのみ. ここでは spam ↔ 他 のみ気にする.
        $now = now();
        $updates = ['status' => $validated['status']];
        if ($validated['status'] === EmailThread::STATUS_SPAM) {
            // 既に spam だった場合は既存の spammed_at を温存 (= 最初の判定時刻を保つ).
            $updates['spammed_at'] = $thread->spammed_at ?: $now;
        } else if ($thread->status === EmailThread::STATUS_SPAM) {
            $updates['spammed_at'] = null;
        }

        // 担当者が未設定なら、ステータス変更したユーザを自動的に担当者にする。
        // 既に他のユーザが担当している場合は尊重して上書きしない。
        if ($thread->assigned_user_id === null && $request->user()) {
            $updates['assigned_user_id'] = $request->user()->id;
        }

        $thread->update($updates);

        // ★ マージ関係にあるスレッドの status を全部揃える。
        //   一方が「受信中」もう一方が「完了」のような片寄り状態になると、
        //   レポート集計に「受信 N 件」と出るのに UI のどこにも見えない事故になる。
        //   - 自分が target なら → 全 source を同じ status に更新
        //   - 自分が source なら → target + 兄弟 source も同じ status に更新
        //   要望: 「マージ後、完了した場合、マージされたものはすべて完了とする」
        try {
            // 自分が target としての関係
            $sourceIds = \App\Models\ThreadMerge::where('target_thread_id', $thread->id)
                ->pluck('source_thread_id_original')
                ->all();
            // 自分が source としての関係 (兄弟 source も含めて揃える)
            $asSource = \App\Models\ThreadMerge::where('source_thread_id_original', $thread->id)->first();
            $targetId = $asSource?->target_thread_id;
            $siblingSourceIds = [];
            if ($targetId) {
                $siblingSourceIds = \App\Models\ThreadMerge::where('target_thread_id', $targetId)
                    ->where('source_thread_id_original', '!=', $thread->id)
                    ->pluck('source_thread_id_original')
                    ->all();
            }
            $idsToSync = array_values(array_unique(array_filter(array_merge(
                $sourceIds,
                $targetId ? [$targetId] : [],
                $siblingSourceIds
            ))));
            if (!empty($idsToSync)) {
                // マージ親族にも spammed_at を伝播する.
                //   spam に揃える時: 既存 spammed_at が無い行だけ now() で埋める (COALESCE)
                //   spam を解く時:   spammed_at = NULL
                $cascadeUpdates = ['status' => $validated['status']];
                if ($validated['status'] === EmailThread::STATUS_SPAM) {
                    $cascadeUpdates['spammed_at'] = \Illuminate\Support\Facades\DB::raw(
                        'COALESCE(spammed_at, ' . \Illuminate\Support\Facades\DB::getPdo()->quote((string) $now) . ')'
                    );
                } else {
                    $cascadeUpdates['spammed_at'] = null;
                }
                \App\Models\EmailThread::whereIn('id', $idsToSync)->update($cascadeUpdates);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('updateStatus: cascade to merge relatives failed', [
                'thread_id' => $thread->id,
                'error'     => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * スレッドを「ゴミ箱に入れる」(論理削除).
     *
     * 旧仕様: $thread->delete() で即ハード DELETE していた.
     * 新仕様: status='trash' + trashed_at=now() を立て、通常一覧から見えなくする.
     *         30 日経過後 (= EmailThread::TRASH_RETENTION_DAYS) に
     *         `mail:purge-trash` コマンドが cascade ハード DELETE する.
     *
     * 復元 (restoreThread) や即時完全削除 (purgeThread) は別エンドポイント.
     *
     * クエリ ?hard=1 が立っている場合だけ旧仕様の即時ハード DELETE を呼ぶ
     * (= ゴミ箱画面の「今すぐ完全削除」ボタンから到達する経路と統合).
     */
    public function deleteThread(\Illuminate\Http\Request $request, EmailThread $thread): JsonResponse
    {
        if ($request->boolean('hard')) {
            // ゴミ箱からの即時完全削除 (cascade ハード DELETE).
            $thread->delete();
            return response()->json(['status' => 'ok', 'hard_deleted' => true]);
        }
        // 通常削除: ゴミ箱送り.
        $thread->forceFill([
            'status'     => EmailThread::STATUS_TRASH,
            'trashed_at' => now(),
        ])->save();
        return response()->json(['status' => 'ok', 'trashed' => true]);
    }

    /**
     * ゴミ箱に入っているスレッドを復元する. status='trash' を解除し、trashed_at をクリア.
     * 復元先 status はリクエスト指定 (既定 'inbox').
     */
    public function restoreThread(\Illuminate\Http\Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:inbox,hold,completed,no_action,pending',
        ]);
        $thread->forceFill([
            'status'     => $validated['status'] ?? EmailThread::STATUS_INBOX,
            'trashed_at' => null,
        ])->save();
        return response()->json(['status' => 'ok', 'restored' => true]);
    }

    /**
     * スレッド内の 1 通のメールだけを「ゴミ箱に入れる」.
     *
     * 旧仕様: $email->delete() で即ハード DELETE していた.
     * 新仕様: emails.trashed_at=now() を立てて論理削除. スレッドが全てゴミ箱化したら
     *         親スレッドも status='trash' に揃える.
     *
     * クエリ ?hard=1 が立っている場合だけ旧仕様の即時ハード DELETE.
     * 旧仕様の「最後の 1 通を消したら親スレッドも消す」挙動は hard 時のみ維持する
     * (論理削除では孤児にならないため不要).
     */
    public function destroyEmail(\Illuminate\Http\Request $request, \App\Models\Email $email): JsonResponse
    {
        if ($request->boolean('hard')) {
            $threadId = $email->thread_id;
            $email->delete();

            $threadDeleted = false;
            if ($threadId) {
                $remaining = \App\Models\Email::where('thread_id', $threadId)->count();
                if ($remaining === 0) {
                    $thread = \App\Models\EmailThread::find($threadId);
                    if ($thread) {
                        $thread->delete();
                        $threadDeleted = true;
                    }
                }
            }
            return response()->json([
                'status'         => 'ok',
                'hard_deleted'   => true,
                'thread_deleted' => $threadDeleted,
            ]);
        }

        // 通常削除: ゴミ箱送り.
        $email->forceFill(['trashed_at' => now()])->save();

        // 親スレッドの「生きているメール」が 0 件になったらスレッドもゴミ箱化する.
        // (個別メールを全部ゴミ箱に入れる = スレッドごとゴミ箱と意味的に等価)
        $threadTrashed = false;
        if ($email->thread_id) {
            $aliveCount = \App\Models\Email::where('thread_id', $email->thread_id)
                ->whereNull('trashed_at')
                ->count();
            if ($aliveCount === 0) {
                $thread = \App\Models\EmailThread::find($email->thread_id);
                if ($thread && $thread->status !== EmailThread::STATUS_TRASH) {
                    $thread->forceFill([
                        'status'     => EmailThread::STATUS_TRASH,
                        'trashed_at' => now(),
                    ])->save();
                    $threadTrashed = true;
                }
            }
        }

        return response()->json([
            'status'         => 'ok',
            'trashed'        => true,
            'thread_trashed' => $threadTrashed,
        ]);
    }

    /**
     * ゴミ箱に入っている個別メールを復元する (trashed_at をクリア).
     * 親スレッドが status='trash' のままなら 'inbox' に戻す (= メールが復元されたなら
     * スレッドもゴミ箱から出すのが自然).
     */
    public function restoreEmail(\App\Models\Email $email): JsonResponse
    {
        $email->forceFill(['trashed_at' => null])->save();
        if ($email->thread_id) {
            $thread = \App\Models\EmailThread::find($email->thread_id);
            if ($thread && $thread->status === EmailThread::STATUS_TRASH) {
                $thread->forceFill([
                    'status'     => EmailThread::STATUS_INBOX,
                    'trashed_at' => null,
                ])->save();
            }
        }
        return response()->json(['status' => 'ok', 'restored' => true]);
    }

    /**
     * ゴミ箱ビュー: ゴミ箱に入っているスレッドと個別メールを一覧で返す.
     *
     * UI からは GET /trash で呼ばれる. クエリ:
     *   ?kind=thread (既定): ゴミ箱化スレッド一覧
     *   ?kind=email         : ゴミ箱化された個別メール一覧 (スレッド本体は生きている)
     *
     * 表示用に「ゴミ箱に入った日時 + あと何日で完全削除されるか」も計算して返す.
     */
    public function trashIndex(\Illuminate\Http\Request $request): JsonResponse
    {
        $kind = $request->string('kind')->toString() ?: 'thread';
        // 管理者設定 (mail_settings.trash_retention_days) を尊重する.
        // mail_settings 行が無い / カラム未存在の環境では 30 日にフォールバック.
        $retentionDays = EmailThread::trashRetentionDays();

        if ($kind === 'email') {
            $emails = \App\Models\Email::whereNotNull('trashed_at')
                ->with('thread')
                ->orderByDesc('trashed_at')
                ->limit(500)
                ->get()
                ->map(function ($e) use ($retentionDays) {
                    $purgeAt = $e->trashed_at ? $e->trashed_at->copy()->addDays($retentionDays) : null;
                    return [
                        'id'           => $e->id,
                        'thread_id'    => $e->thread_id,
                        'thread_subject' => $e->thread?->subject,
                        'subject'      => $e->subject,
                        'from_label'   => $e->from_label,
                        'received_at'  => $e->received_at?->format('Y/m/d H:i'),
                        'trashed_at'   => $e->trashed_at?->format('Y/m/d H:i'),
                        'purge_at'     => $purgeAt?->format('Y/m/d H:i'),
                        'days_left'    => $purgeAt ? max(0, now()->diffInDays($purgeAt, false)) : null,
                    ];
                });
            return response()->json(['kind' => 'email', 'items' => $emails, 'retention_days' => $retentionDays]);
        }

        $threads = EmailThread::where('status', EmailThread::STATUS_TRASH)
            ->whereNotNull('trashed_at')
            ->with('latestEmail', 'customer')
            ->orderByDesc('trashed_at')
            ->limit(500)
            ->get()
            ->map(function ($t) use ($retentionDays) {
                $purgeAt = $t->trashed_at ? $t->trashed_at->copy()->addDays($retentionDays) : null;
                return [
                    'id'             => $t->id,
                    'subject'        => $t->subject,
                    'last_email_at'  => $t->last_email_at?->format('Y/m/d H:i'),
                    'customer_name'  => $t->customer?->name,
                    'trashed_at'     => $t->trashed_at?->format('Y/m/d H:i'),
                    'purge_at'       => $purgeAt?->format('Y/m/d H:i'),
                    'days_left'      => $purgeAt ? max(0, now()->diffInDays($purgeAt, false)) : null,
                ];
            });
        return response()->json(['kind' => 'thread', 'items' => $threads, 'retention_days' => $retentionDays]);
    }

    /**
     * 指定メールを現在のスレッドから分離して、新しいスレッドに移動する.
     *
     * 用途: 1 つのスレッドに紛れ込んでしまったメールを「別スレッドとして独立させたい」時.
     *       例: 自動振り分けや過去の bug で同一スレッドに集約されたが本来は別件のメール.
     *
     * 仕様:
     *   - email.thread_id を新規スレッドに付け替える (削除はしない)
     *   - 新スレッドの subject は email.subject (Re:/Fwd: を除去).
     *   - 新スレッドの status は inbox 開始 (ユーザは新規に確認したいはずなので).
     *   - 新スレッドの last_email_at は email.received_at.
     *   - 新スレッドにはルーム紐付け / customer_id / assigned_user_id を引き継がない.
     *     (ユーザが「この 1 通だけ独立させたい」と言ってる以上、文脈は親と切り離す方が安全)
     *   - 親スレッドが空になっても自動削除はしない (スレッドメッセージ等の付随データが
     *     残っている場合があるため). UI 側で確認して必要なら手動で削除.
     *   - 親スレッドの last_email_at を残メールの最新値に再計算.
     *   - 新スレッドに ticket_number を自動付与.
     */
    public function detachEmail(\App\Models\Email $email): JsonResponse
    {
        $originalThreadId = $email->thread_id;
        if (!$originalThreadId) {
            return response()->json(['status' => 'error', 'message' => 'このメールはスレッドに属していません'], 422);
        }

        $rawSubject = (string) ($email->subject ?: '(件名なし)');
        // Re: / Fwd: / Fw: の先頭プレフィックスは除去 (新規取り込み時と同じ正規化).
        $clean = preg_replace('/^(Re:\s*|Fwd:\s*|Fw:\s*)+/iu', '', $rawSubject) ?? $rawSubject;
        $clean = trim($clean);
        if ($clean === '') $clean = $rawSubject;

        $newThreadId = null;
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($email, $originalThreadId, $clean, &$newThreadId) {
                $now = now();
                $newThreadId = \Illuminate\Support\Facades\DB::table('email_threads')->insertGetId([
                    'subject'       => mb_substr($clean, 0, 255),
                    'status'        => 'inbox',
                    'last_email_at' => $email->received_at ?? $now,
                    'is_pinned'     => 0,
                    'is_manual_upload' => 0,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                // メールを新スレッドへ付け替え
                \App\Models\Email::where('id', $email->id)->update([
                    'thread_id'  => $newThreadId,
                    'updated_at' => $now,
                ]);

                // 新スレッドにチケット番号付与
                $newThread = \App\Models\EmailThread::find($newThreadId);
                if ($newThread) $newThread->ensureTicketNumber();

                // 親スレッドの last_email_at を残メールの最新で再計算
                $latest = \Illuminate\Support\Facades\DB::table('emails')
                    ->where('thread_id', $originalThreadId)
                    ->orderByDesc('received_at')->orderByDesc('id')
                    ->first(['received_at']);
                if ($latest) {
                    \Illuminate\Support\Facades\DB::table('email_threads')
                        ->where('id', $originalThreadId)
                        ->update(['last_email_at' => $latest->received_at, 'updated_at' => $now]);
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('detachEmail failed', [
                'email_id' => $email->id, 'thread_id' => $originalThreadId, 'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error', 'message' => '分離に失敗しました: ' . $e->getMessage()], 500);
        }

        $remaining = \App\Models\Email::where('thread_id', $originalThreadId)->count();
        // 分離先スレッドの情報を返し、フロント側で「削除ではなく移動した」ことをはっきり示せるようにする.
        $newThread = $newThreadId ? \App\Models\EmailThread::find($newThreadId) : null;
        return response()->json([
            'status'                    => 'ok',
            'new_thread_id'             => $newThreadId,
            'new_thread_subject'        => $newThread?->subject,
            'new_thread_ticket_number'  => $newThread?->ticket_number,
            'original_thread_id'        => $originalThreadId,
            'original_remaining'        => $remaining,
            'original_thread_empty'     => $remaining === 0,
            // ★ メール本体は削除していない (thread_id を付け替えたのみ).
            //   UI のメッセージで「データは保持されています」と明示するためのフラグ.
            'email_preserved'           => true,
        ]);
    }

    public function bulkAssignCustomer(Request $request): JsonResponse
    {
        $request->validate([
            'thread_ids' => 'required|array|min:1',
            'customer_id' => 'required',
        ]);

        $customerId = $request->customer_id === 'none' ? null : $request->customer_id;

        EmailThread::whereIn('id', $request->thread_ids)->update(['customer_id' => $customerId]);

        // 顧客割り当て後に、その顧客名と一致する共有ルームへ自動振り分け。
        // (customer_id を立てた全スレッドが対象。bundleByCustomer は冪等)
        if ($customerId !== null) {
            try {
                $customer = \App\Models\Customer::find($customerId);
                if ($customer) {
                    \App\Services\ChatRoomAutoBundler::bundleByCustomer($customer);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('auto-bundle on bulk assign failed', [
                    'customer_id' => $customerId, 'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'updated' => count($request->thread_ids)
        ]);
    }
}
