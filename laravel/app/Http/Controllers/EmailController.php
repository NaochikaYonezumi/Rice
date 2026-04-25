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

    // AI案生成 (スレッド全体コンテキスト + URLスクレイピング対応)
    public function askAi(Request $request, Email $email): JsonResponse
    {
        $userPrompt = trim($request->input('prompt', ''));
        $scrapeUrl  = trim($request->input('url', ''));

        // ユーザー指示がなければデフォルトプロンプトを使用
        if (!$userPrompt) {
            $aiSettings = AiSetting::getSettings();
            $userPrompt = $aiSettings->default_reply_prompt
                ?: 'このスレッドの内容を把握した上で、丁寧で的確な返信を日本語で作成してください。';
        }

        // スレッド全体の履歴を構築
        $thread = $email->thread;
        $threadContext = "=== スレッド: " . ($thread->subject ?? $email->subject) . " ===\n\n";
        $emails = $thread
            ? $thread->emails()->orderBy('received_at')->get()
            : collect([$email]);

        foreach ($emails as $e) {
            $threadContext .= "差出人: {$e->from_label}\n";
            $threadContext .= "宛先: " . ($e->to_address ?? '') . "\n";
            $threadContext .= "日時: " . ($e->received_at?->format('Y/m/d H:i') ?? '不明') . "\n";
            $body = $e->body_text ?: strip_tags($e->body_html ?? '');
            $threadContext .= "本文:\n" . mb_substr($body, 0, 2000) . "\n";
            $threadContext .= "---\n\n";
        }

        // URLスクレイピング (オプション)
        $scrapedContent = '';
        if ($scrapeUrl) {
            try {
                $html = Http::timeout(15)->get($scrapeUrl)->body();
                $text = mb_substr(strip_tags($html), 0, 3000);
                $scrapedContent = "=== 参考URL内容 ({$scrapeUrl}) ===\n{$text}\n\n";
            } catch (\Throwable) {}
        }

        $question = "{$threadContext}{$scrapedContent}指示: {$userPrompt}";
        $result   = $this->ragApi->query($question);
        $answer   = is_array($result) ? ($result['answer'] ?? json_encode($result, JSON_UNESCAPED_UNICODE)) : (string) $result;

        return response()->json(['answer' => $answer]);
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
            'last_email_at' => $t->last_email_at?->diffForHumans(),
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
}
