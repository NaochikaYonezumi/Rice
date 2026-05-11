<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\PendingEmail;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\MailClient\Services\EmailFetcher;

class EmailController extends Controller
{
    public function __construct(private RagApiService $ragApi) {}

    public function index() { return view('emails.index', ['isPinnedView' => false]); }

    public function pinned() { return view('emails.index', ['isPinnedView' => true]); }

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

        // 並列コンテキスト取得
        $responses = Http::pool(fn ($pool) => [
            $pool->as('kb')->get("http://rag-api:8000/query", ['query' => $email->subject . " " . Str::limit($email->body_text, 100)]),
            $pool->as('reports')->get("http://rag-api:8000/reports", ['query' => $email->subject]),
        ]);

        $kbContent = $responses['kb']->ok() ? ($responses['kb']->json()['answer'] ?? '') : '';
        $reportContent = $responses['reports']->ok() ? ($responses['reports']->json()['content'] ?? '') : '';

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

    // 返信予約 (TO/CC/BCC/添付対応)
    public function reply(Request $request, Email $email): JsonResponse
    {
        $validated = $request->validate([
            'from_address' => 'nullable|string|email',
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'body' => 'required|string',
            'created_by' => 'nullable|string',
            'attachments.*' => 'file|max:20480', // 20MB
        ]);

        $pending = new PendingEmail();
        $pending->in_reply_to_email_id = $email->id;
        $pending->reply_type = PendingEmail::TYPE_REPLY;
        $pending->from_address = $validated['from_address'] ?? null;
        $pending->to_address = $validated['to'];
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;
        $pending->subject = "Re: " . $email->subject;
        $pending->body = $validated['body'];
        $pending->status = PendingEmail::STATUS_PENDING;
        $pending->created_by = $validated['created_by'] ?? (auth()->user()->name ?? '米住 直親');
        $pending->created_by_user_id = auth()->id();
        
        $paths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store('attachments/pending', 'private');
            }
        }
        $pending->attachment_paths = $paths;
        $pending->save();

        return response()->json(['status' => 'ok', 'id' => $pending->id]);
    }

    // 新規作成予約 (承認待ち)
    public function compose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_address' => 'nullable|string|email',
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'body' => 'required|string',
            'subject' => 'nullable|string',
            'created_by' => 'nullable|string',
            'attachments.*' => 'file|max:20480',
        ]);

        $pending = new PendingEmail();
        $pending->reply_type = PendingEmail::TYPE_COMPOSE;
        $pending->from_address = $validated['from_address'] ?? null;
        $pending->to_address = $validated['to'];
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;
        $pending->subject = $validated['subject'] ?? '(無題)';
        $pending->body = $validated['body'];
        $pending->status = PendingEmail::STATUS_PENDING;
        $pending->created_by = $validated['created_by'] ?? (auth()->user()->name ?? '米住 直親');
        $pending->created_by_user_id = auth()->id();
        
        $paths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store('attachments/pending', 'private');
            }
        }
        $pending->attachment_paths = $paths;
        $pending->save();

        return response()->json(['status' => 'ok', 'id' => $pending->id]);
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

        return response()->json(['thread' => $thread, 'emails' => $emails, 'merges' => $merges]);
    }

    public function updateStatus(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', \App\Models\EmailThread::STATUSES),
        ]);
        $newStatus = $validated['status'];
        $payload = ['status' => $newStatus];

        // 完了に変更する際、担当者が未設定であれば押下したユーザを担当者として自動アサイン
        $autoAssigned = false;
        if ($newStatus === 'completed' && empty($thread->assigned_user_id) && auth()->id()) {
            $payload['assigned_user_id'] = auth()->id();
            $autoAssigned = true;
        }

        $thread->update($payload);

        $response = ['status' => 'ok', 'auto_assigned' => $autoAssigned];
        if ($autoAssigned) {
            $thread->load('assignee');
            $response['assigned_user_id'] = $thread->assigned_user_id;
            $response['assignee'] = $thread->assignee
                ? ['id' => $thread->assignee->id, 'name' => $thread->assignee->name]
                : null;
        }

        return response()->json($response);
    }

    public function deleteThread(EmailThread $thread): JsonResponse
    {
        $thread->delete();
        return response()->json(['status' => 'ok']);
    }

    public function updateTags(Request $request, EmailThread $thread): JsonResponse
    {
        $thread->update(['tags' => array_values(array_unique($request->tags))]);
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
