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
    public function __construct(private RagApiService $ragApi) {}

    public function index() { return view('emails.index', ['isPinnedView' => false]); }

    public function pinned() { return view('emails.index', ['isPinnedView' => true]); }

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
        ]);
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

        $subject = $email->subject ?? '';
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
        ]);
    }

    public function fetch(EmailFetcher $fetcher): JsonResponse
    {
        try {
            $count = $fetcher->fetch();
            return response()->json(['status' => 'ok', 'count' => $count]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ], 500);
        }
    }

    // AIアシスタントによる生成 (スキル選択・コンテキスト強化)
    public function askAi(Request $request, Email $email): JsonResponse
    {
        $userPrompt = trim($request->input('prompt', ''));
        $skillKey   = $request->input('skill', 'reply');
        $skills     = config('ai_skills.skills');
        $selectedSkill = $skills[$skillKey] ?? $skills['reply'];

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
        $agentName = $aiSettings->agent_name ?: '米住 直親';
        $signature = $aiSettings->agent_signature ?: "---\nPaperCutサポート窓口\n米住 直親";

        $finalPrompt = "【システム指示】\n{$selectedSkill['system_prompt']}\n\n";
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

        // RAG API経由で生成
        $result = $this->ragApi->query($finalPrompt, 3, $aiSettings->default_provider, $aiSettings->default_model);

        return response()->json([
            'answer' => $result['answer'] ?? '',
            'skill_used' => $selectedSkill['name'],
            'sources' => [
                'kb' => !empty($kbContent),
                'reports' => !empty($reportContent)
            ]
        ]);
    }

    /**
     * 新規作成画面用の AI アシスタント (返信対象なし)
     *   入力: subject, body, to, prompt, skill, mask_pii
     */
    public function askAiCompose(Request $request): JsonResponse
    {
        $userPrompt = trim($request->input('prompt', ''));
        $subject    = trim((string) $request->input('subject', ''));
        $body       = trim((string) $request->input('body', ''));
        $to         = trim((string) $request->input('to', ''));
        $skillKey   = $request->input('skill', 'reply');
        $skills     = config('ai_skills.skills');
        $selectedSkill = $skills[$skillKey] ?? $skills['reply'];

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
        $agentName  = $aiSettings->agent_name ?: '米住 直親';
        $signature  = $aiSettings->agent_signature ?: "---\nPaperCutサポート窓口\n米住 直親";

        $finalPrompt  = "【システム指示】\n{$selectedSkill['system_prompt']}\n\n";
        $finalPrompt .= "【モード】新規作成 (返信対象なし)\n\n";
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

        $result = $this->ragApi->query($finalPrompt, 3, $aiSettings->default_provider, $aiSettings->default_model);

        return response()->json([
            'answer'     => $result['answer'] ?? '',
            'skill_used' => $selectedSkill['name'],
            'sources'    => [
                'kb'      => !empty($kbContent),
                'reports' => !empty($reportContent),
            ],
        ]);
    }

    /**
     * スレッド要約: 指定スレッドの全メールを参照して要約を生成
     */
    public function summarizeThread(EmailThread $thread): JsonResponse
    {
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
        $prompt  = "【システム指示】\n";
        $prompt .= "あなたはサポート窓口担当者です。以下のメールスレッドを日本語で要約してください。\n";
        $prompt .= "出力フォーマット:\n";
        $prompt .= "1. 概要 (3〜5行で何のスレッドか)\n";
        $prompt .= "2. 経緯 (時系列で 5〜8 行の箇条書き)\n";
        $prompt .= "3. 未解決事項 / ネクストアクション (箇条書き、なければ「なし」)\n";
        $prompt .= "4. 重要な日付・金額・人物・固有名詞 (列挙)\n";
        $prompt .= "返信案や挨拶文は不要、要約のみ。\n\n";
        $prompt .= "【スレッド件名】\n" . ($thread->subject ?: '(件名なし)') . "\n";
        if ($thread->ticket_number) {
            $prompt .= "【チケット番号】\n[#" . $thread->ticket_number . "]\n";
        }
        $prompt .= "【メール総数】" . $emails->count() . " 通\n\n";
        $prompt .= "【スレッド本文】\n" . $threadContext;

        $result = $this->ragApi->query($prompt, 1, $aiSettings->default_provider, $aiSettings->default_model);

        return response()->json([
            'status'      => 'ok',
            'summary'     => $result['answer'] ?? '',
            'email_count' => $emails->count(),
            'subject'     => $thread->subject,
            'ticket'      => $thread->ticket_number,
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
            'body' => 'required_without:save_as_draft|nullable|string',
            'created_by' => 'nullable|string',
            'approver_id' => 'nullable|integer|exists:users,id',
            'draft_id' => 'nullable|integer|exists:pending_emails,id',
            // Phase 6-3: AI 採用率算定で参照する元 AI ログ
            'ai_log_id' => 'nullable|integer|exists:ext_ai_logs,id',
            'attachments.*' => 'file|max:20480', // 20MB
            // 下書き編集時に保持する既存添付のパス (storePendingAttachments で保存されたもの)
            'keep_attachments'   => 'nullable|array',
            'keep_attachments.*' => 'string|max:512',
        ]);

        $pending = new PendingEmail();
        $pending->in_reply_to_email_id = $email->id;
        $pending->reply_type = PendingEmail::TYPE_REPLY;
        $pending->from_address = $validated['from_address'] ?? null;
        $pending->to_address = $validated['to'] ?? '';
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;
        $pending->subject = "Re: " . $email->subject;
        $pending->body = $validated['body'] ?? '';
        $saveAsDraft = $request->boolean('save_as_draft');
        $pending->status = $saveAsDraft ? PendingEmail::STATUS_DRAFT : PendingEmail::STATUS_PENDING;
        $pending->created_by = $validated['created_by'] ?? (auth()->user()->name ?? '米住 直親');
        $pending->created_by_user_id = auth()->id();
        $pending->target_approver_user_id = $validated['approver_id'] ?? null;
        $pending->ai_log_id = $validated['ai_log_id'] ?? null;

        // 既存添付 (下書き編集元) + 新規アップロードファイルを統合
        $pending->attachment_paths = $this->resolvePendingAttachments($request, $validated['draft_id'] ?? null);
        $pending->save();

        if (!$saveAsDraft) {
            $this->notifyAdmins($pending);
        }

        // 編集元の下書きが指定されている場合は削除
        $this->deleteSourceDraftIfAny($validated['draft_id'] ?? null);

        return response()->json(['status' => 'ok', 'id' => $pending->id]);
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
            'body' => 'required_without:save_as_draft|nullable|string',
            'subject' => 'nullable|string',
            'created_by' => 'nullable|string',
            'approver_id' => 'nullable|integer|exists:users,id',
            'draft_id' => 'nullable|integer|exists:pending_emails,id',
            // Phase 6-3: AI 採用率算定用
            'ai_log_id' => 'nullable|integer|exists:ext_ai_logs,id',
            'attachments.*' => 'file|max:20480',
            // 下書き編集時に保持する既存添付のパス
            'keep_attachments'   => 'nullable|array',
            'keep_attachments.*' => 'string|max:512',
        ]);

        $pending = new PendingEmail();
        $pending->reply_type = PendingEmail::TYPE_COMPOSE;
        $pending->from_address = $validated['from_address'] ?? null;
        $pending->to_address = $validated['to'] ?? '';
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;
        $pending->subject = $validated['subject'] ?: '(無題)';
        $pending->body = $validated['body'] ?? '';
        $saveAsDraft = $request->boolean('save_as_draft');
        $pending->status = $saveAsDraft ? PendingEmail::STATUS_DRAFT : PendingEmail::STATUS_PENDING;
        $pending->created_by = $validated['created_by'] ?? (auth()->user()->name ?? '米住 直親');
        $pending->created_by_user_id = auth()->id();
        $pending->target_approver_user_id = $validated['approver_id'] ?? null;
        $pending->ai_log_id = $validated['ai_log_id'] ?? null;

        // 既存添付 (下書き編集元) + 新規アップロードファイルを統合
        $pending->attachment_paths = $this->resolvePendingAttachments($request, $validated['draft_id'] ?? null);
        $pending->save();

        if (!$saveAsDraft) {
            $this->notifyAdmins($pending);
        }

        // 編集元の下書きが指定されている場合は削除
        $this->deleteSourceDraftIfAny($validated['draft_id'] ?? null);

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

    public function search(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $tags = array_values(array_filter((array) $request->input('tags', [])));
        $status = $request->query('status');
        $cid = $request->input('customer_id');
        $gid = $request->input('group_id');
        $allStatus = $request->boolean('all_status');
        $isPinned = $request->boolean('is_pinned');
        $assigneeId = $request->input('assigned_user_id');
        $sortKey = $request->input('sort_key', 'last_email_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $threads = EmailThread::with('latestEmail', 'customer', 'assignee')->withCount('threadMerges')
            ->whereNotIn('id', \App\Models\ThreadMerge::select('source_thread_id_original'))
            ->orderBy($sortKey, $sortOrder);

        // 全表示トグルがOFFの場合のみステータスフィルタを適用
        if (!$allStatus && $status) {
            $threads->where('status', $status);
        }

        if ($isPinned) {
            $threads->where('is_pinned', true);
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
        foreach ($tags as $tag) {
            $threads->whereJsonContains('tags', $tag);
        }

        $result = $threads->get()->map(fn($t) => [
            'id' => $t->id,
            'subject' => $t->subject,
            'ticket_number' => $t->ticket_number,
            'status' => $t->status,
            'is_pinned' => $t->is_pinned,
            'assigned_user_id' => $t->assigned_user_id,
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'last_email_at' => $t->last_email_at?->format('Y/m/d H:i'),
            'tags' => $t->tags ?? [],
            'customer_id' => $t->customer_id,
            'customer' => $t->customer ? ['name' => $t->customer->name] : null,
            'latest_email' => $t->latestEmail ? [
                'from_label' => $t->latestEmail->from_label,
                'from_address' => $t->latestEmail->from_address,
                'plain_body' => Str::limit($t->latestEmail->plain_body, 50),
            ] : null,
        ]);

        return response()->json($result);
    }

    public function togglePin(Request $request, EmailThread $thread): JsonResponse
    {
        $isPinned = $request->has('is_pinned') ? $request->boolean('is_pinned') : !$thread->is_pinned;
        $thread->update(['is_pinned' => $isPinned]);
        return response()->json(['status' => 'ok', 'is_pinned' => $thread->is_pinned]);
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

        $emails = \App\Models\Email::whereIn('thread_id', $threadIds)->with('attachments', 'thread')->orderBy('received_at', 'asc')->get()->map(fn($e) => [
            'id' => $e->id,
            'thread_id' => $e->thread_id,
            'thread_status' => $e->thread->status ?? 'inbox',
            'thread_is_pinned' => $e->thread->is_pinned ?? false,
            'subject' => $e->subject,
            'from_label' => $e->from_label,
            'from_address' => $e->from_address,
            'to_address' => $e->to_address,
            'cc' => $e->cc,
            'body_html' => $e->body_html,
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

        return response()->json(['thread' => $thread, 'emails' => $emails, 'merges' => $merges, 'pending_approvals' => $pendingApprovals]);
    }

    public function updateStatus(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:inbox,hold,completed,no_action,pending',
        ]);
        $thread->update(['status' => $validated['status']]);
        return response()->json(['status' => 'ok']);
    }

    public function deleteThread(EmailThread $thread): JsonResponse
    {
        $thread->delete();
        return response()->json(['status' => 'ok']);
    }

    public function updateTags(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'tags'   => 'nullable|array|max:50',
            'tags.*' => 'string|max:64',
        ]);
        $tags = array_values(array_unique($validated['tags'] ?? []));
        $thread->update(['tags' => $tags]);
        return response()->json(['status' => 'ok']);
    }

    public function bulkAssignCustomer(Request $request): JsonResponse
    {
        $request->validate([
            'thread_ids' => 'required|array|min:1',
            'customer_id' => 'required',
        ]);

        $customerId = $request->customer_id === 'none' ? null : $request->customer_id;

        EmailThread::whereIn('id', $request->thread_ids)->update(['customer_id' => $customerId]);

        return response()->json([
            'success' => true,
            'updated' => count($request->thread_ids)
        ]);
    }
}
