<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\MailSetting;
use App\Services\EmailFetchService;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function __construct(private RagApiService $ragApi) {}

    public function index()
    {
        return view('emails.index');
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $tags = array_values(array_filter((array) $request->input('tags', [])));

        $threads = EmailThread::with('latestEmail')->orderByDesc('last_email_at');

        if ($query) {
            $threads->where(function ($q) use ($query) {
                $q->where('subject', 'like', "%{$query}%")
                  ->orWhereHas('emails', function ($q2) use ($query) {
                      $q2->where('from_name', 'like', "%{$query}%")
                         ->orWhere('from_address', 'like', "%{$query}%")
                         ->orWhere('body_text', 'like', "%{$query}%");
                  });
            });
        }

        foreach ($tags as $tag) {
            $threads->whereJsonContains('tags', $tag);
        }

        $result = $threads->get()->map(fn($t) => [
            'id'           => $t->id,
            'subject'      => $t->subject,
            'last_email_at' => $t->last_email_at?->diffForHumans(),
            'tags'         => $t->tags ?? [],
            'latest_email' => $t->latestEmail ? [
                'id'         => $t->latestEmail->id,
                'from_label' => $t->latestEmail->from_label,
                'plain_body' => \Illuminate\Support\Str::limit($t->latestEmail->plain_body, 60),
                'is_read'    => $t->latestEmail->is_read,
            ] : null,
        ]);

        return response()->json($result);
    }

    public function updateTags(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'tags'   => 'required|array',
            'tags.*' => 'string|max:50',
        ]);

        $thread->update(['tags' => array_values(array_unique($validated['tags']))]);

        return response()->json(['tags' => $thread->fresh()->tags ?? []]);
    }

    public function thread(EmailThread $thread): JsonResponse
    {
        $emails = $thread->emails()->get()->map(fn($e) => [
            'id' => $e->id,
            'subject' => $e->subject,
            'from_label' => $e->from_label,
            'from_address' => $e->from_address,
            'to_address' => $e->to_address,
            'body_html' => $e->body_html,
            'plain_body' => $e->plain_body,
            'received_at' => $e->received_at?->format('Y/m/d H:i'),
            'is_read' => $e->is_read,
        ]);

        return response()->json(['thread' => $thread, 'emails' => $emails]);
    }

    public function show(Email $email): JsonResponse
    {
        if (!$email->is_read) {
            $email->update(['is_read' => true]);
        }

        return response()->json([
            'id' => $email->id,
            'subject' => $email->subject,
            'from_label' => $email->from_label,
            'from_address' => $email->from_address,
            'to_address' => $email->to_address,
            'body_html' => $email->body_html,
            'plain_body' => $email->plain_body,
            'received_at' => $email->received_at?->format('Y/m/d H:i'),
            'thread_id' => $email->thread_id,
        ]);
    }

    public function askAi(Request $request, Email $email): JsonResponse
    {
        $request->validate(['question' => 'nullable|string|max:2000']);

        $emailContext = "差出人: {$email->from_address}\n"
            . "件名: {$email->subject}\n"
            . "受信日時: {$email->received_at?->format('Y/m/d H:i')}\n\n"
            . "本文:\n{$email->plain_body}";

        $question = $request->input('question')
            ?: "このメールに対して適切な返信文を日本語で作成してください。";

        $prompt = "以下のメール内容を参照して、質問に答えてください。\n\n"
            . "=== メール内容 ===\n{$emailContext}\n\n"
            . "=== 質問 ===\n{$question}";

        try {
            $result = $this->ragApi->query($prompt, topK: 5);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reply(Request $request, Email $email): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:100000',
            'to'   => 'required|email',
            'cc'   => 'nullable|string|max:1000',
        ]);

        $settings  = MailSetting::getSettings();
        $subject   = preg_match('/^Re:/i', $email->subject) ? $email->subject : 'Re: ' . $email->subject;
        $messageId = $email->message_id;

        $this->applySmtpConfig($settings);

        try {
            Mail::send([], [], function ($message) use ($validated, $subject, $messageId, $settings) {
                $message
                    ->to($validated['to'])
                    ->from($settings->smtp_from_address, $settings->smtp_from_name)
                    ->subject($subject)
                    ->text($validated['body']);

                if (!empty($validated['cc'])) {
                    $message->cc(array_map('trim', explode(',', $validated['cc'])));
                }

                if ($messageId) {
                    $message->getHeaders()
                        ->addTextHeader('In-Reply-To', $messageId)
                        ->addTextHeader('References', $messageId);
                }
            });

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function applySmtpConfig(MailSetting $settings): void
    {
        config([
            'mail.mailers.smtp.host'       => $settings->smtp_host,
            'mail.mailers.smtp.port'       => $settings->smtp_port,
            'mail.mailers.smtp.encryption' => $settings->smtp_encryption === 'null' ? null : $settings->smtp_encryption,
            'mail.mailers.smtp.username'   => $settings->smtp_username,
            'mail.mailers.smtp.password'   => $settings->smtp_password,
            'mail.from.address'            => $settings->smtp_from_address,
            'mail.from.name'               => $settings->smtp_from_name,
        ]);

        // メーラーのインスタンスキャッシュをリセット
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
    }

    public function fetch(EmailFetchService $service): JsonResponse
    {
        try {
            $count = $service->fetch();
            return response()->json(['status' => 'ok', 'imported' => $count]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
