<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\PendingEmail;
use App\Services\EmailFetchService;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailController extends Controller
{
    public function __construct(private RagApiService $ragApi) {}

    public function index() { return view('emails.index'); }

    // AIアシスタントによる返信案・分析生成
    public function askAi(Request $request, Email $email): JsonResponse
    {
        $userPrompt = trim($request->input('prompt', ''));
        $scrapeUrl  = trim($request->input('url', ''));
        
        // 画面上の現在の入力値
        $currentTo  = $request->input('current_to', []);
        $currentCc  = $request->input('current_cc', []);
        $currentBcc = $request->input('current_bcc', []);

        // AI設定
        $aiSettings = AiSetting::getSettings();
        if (!$userPrompt) {
            $userPrompt = $aiSettings->default_reply_prompt ?: '丁寧で的確な返信を日本語で作成してください。';
        }

        // 元メール情報の構築
        $originalEmail = [
            'from'    => $email->from_address,
            'to'      => $email->to_address_array ?? [$email->to_address],
            'cc'      => $email->cc_address_array ?? [],
            'subject' => $email->subject,
            'body'    => mb_substr($email->body_text ?: strip_tags($email->body_html ?? ''), 0, 3000),
        ];

        // スレッド履歴の要約 (コンテキスト用)
        $threadContext = "";
        if ($email->thread) {
            $threadEmails = $email->thread->emails()->orderBy('received_at')->get();
            foreach ($threadEmails as $te) {
                if ($te->id === $email->id) continue;
                $threadContext .= "--- 過去のメール ({$te->received_at}) ---\n";
                $threadContext .= "From: {$te->from_label}\n";
                $threadContext .= "本文: " . Str::limit($te->body_text ?: strip_tags($te->body_html ?? ''), 500) . "\n\n";
            }
        }

        // 参考URL内容
        $scrapedContent = "";
        if ($scrapeUrl) {
            try {
                $html = Http::timeout(10)->get($scrapeUrl)->body();
                $scrapedContent = "=== 参考URL ({$scrapeUrl}) ===\n" . mb_substr(strip_tags($html), 0, 2000) . "\n\n";
            } catch (\Throwable) {}
        }

        // AIへの指示書 (システムプロンプト的な巨大な指示)
        $prompt = "【役割】\nあなたは「PaperCutサポート窓口」担当者（米住 直親）のメール作成支援AIです。\n";
        $prompt .= "以下の情報をもとに、指定されたJSON形式でのみ回答してください。\n\n";
        $prompt .= "【入力情報】\n";
        $prompt .= "- current_to: " . json_encode($currentTo) . "\n";
        $prompt .= "- current_cc: " . json_encode($currentCc) . "\n";
        $prompt .= "- current_bcc: " . json_encode($currentBcc) . "\n";
        $prompt .= "- original_email: " . json_encode($originalEmail, JSON_UNESCAPED_UNICODE) . "\n";
        $prompt .= "- user_email: zumin0512@gmail.com\n";
        $prompt .= "- thread_history: \n{$threadContext}\n";
        $prompt .= "- extra_context: \n{$scrapedContent}\n";
        $prompt .= "- user_instruction: {$userPrompt}\n\n";
        
        $prompt .= "【出力形式・補完ロジック・各列の内容指示】\n";
        $prompt .= "1. auto_fill: 元メールのCC/BCCから、現在の入力欄に不足しているものを抽出。自分(zumin0512@gmail.com)と送信元は除外。\n";
        $prompt .= "2. columns.left (コンテキスト): 元メール要点、送信先属性(販売店/SIer/エンドユーザー)、やり取り傾向を日本語で記載。\n";
        $prompt .= "3. columns.center (下書き): 返信文面。件名は具体的名に変更可。属性に応じた敬語。PaperCut MFに関する正確な記述。具体的指示があれば厳守。\n";
        $prompt .= "4. columns.right (提案・確認): 送信前確認事項、次アクション、トーン変更理由を箇条書きで。\n\n";
        
        $prompt .= "【絶対制約】\n";
        $prompt .= "- 出力は指定のJSONスキーマのみ。説明文や```jsonなどは一切不要。\n";
        $prompt .= "- ソースにない技術情報は創作しない。不確かな場合は「こちらで確認が必要です」と明記。\n";
        $prompt .= "- 日時・タイムゾーンは日本時間(JST)で考慮。\n\n";
        
        $prompt .= "JSONレスポンスを開始してください:";

        // RAG API経由でLLMに問い合わせ (トップKは小さめで十分)
        $result = $this->ragApi->query($prompt, 3, $aiSettings->default_provider, $aiSettings->default_model);
        
        $answer = $result['answer'] ?? '';
        
        // AIがMarkdownブロックで返してきた場合のクレンジング
        $json = preg_replace('/^```json\s*|```$/', '', trim($answer));
        
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return response()->json($decoded);
        } catch (\JsonException $e) {
            // パース失敗時は生のテキストを返す（フロントエンドでフォールバック）
            return response()->json([
                'error' => 'AIの回答がJSON形式ではありませんでした。',
                'raw_text' => $answer
            ], 500);
        }
    }

    // 返信予約 (TO/CC/BCC/添付対応)
    public function reply(Request $request, Email $email): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'body' => 'required|string',
            'attachments.*' => 'file|max:20480', // 20MB
        ]);

        $pending = new PendingEmail();
        $pending->email_thread_id = $email->email_thread_id;
        $pending->to = $validated['to'];
        $pending->cc = $validated['cc'] ?? null;
        $pending->bcc = $validated['bcc'] ?? null;
        $pending->subject = "Re: " . $email->subject;
        $pending->body = $validated['body'];
        $pending->status = 'pending';
        
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

        $threads = EmailThread::with('latestEmail', 'customer')->orderBy('last_email_at', 'desc');

        if ($status) $threads->where('status', $status);
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
            'last_email_at' => $t->last_email_at?->format('Y/m/d H:i'),
            'tags' => $t->tags ?? [],
            'customer_id' => $t->customer_id,
            'latest_email' => $t->latestEmail ? [
                'from_label' => $t->latestEmail->from_label,
                'plain_body' => Str::limit($t->latestEmail->plain_body, 50),
            ] : null,
        ]);

        return response()->json($result);
    }

    public function thread(EmailThread $thread): JsonResponse
    {
        $thread->load('customer');
        $emails = $thread->emails()->with('attachments')->get()->map(fn($e) => [
            'id' => $e->id,
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
        $thread->update(['status' => $request->status]);
        return response()->json(['status' => 'ok']);
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
