<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\MailSetting;
use App\Models\PendingEmail;
use App\Services\EmailFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PendingEmailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', PendingEmail::STATUS_PENDING);
        $query = PendingEmail::where('status', $status);

        if ($request->has('customer_id')) {
            $query->whereHas('inReplyToEmail.thread', function($q) use ($request) {
                if ($request->customer_id === 'none') {
                    $q->whereNull('customer_id');
                } else {
                    $q->where('customer_id', $request->customer_id);
                }
            });
        }

        $pending = $query->with(['inReplyToEmail.thread'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'reply_type'       => $p->reply_type,
                'reply_type_label' => $p->reply_type_label,
                'to_address'       => $p->to_address,
                'cc'               => $p->cc,
                'bcc'              => $p->bcc,
                'subject'          => $p->subject,
                'body'             => $p->body,
                'body_preview'     => $p->body_preview,
                'created_at'       => $p->created_at?->format('Y/m/d H:i'),
                'created_by'       => $p->created_by,
                'memo'             => $p->memo,
                'attachments'      => collect($p->attachment_paths ?? [])->map(
                    fn($a) => [
                        'filename' => basename($a), 
                        'size' => round(Storage::disk('private')->exists($a) ? Storage::disk('private')->size($a) / 1024 : 0, 1) . ' KB'
                    ]
                )->values(),
                'in_reply_to'      => $p->inReplyToEmail ? [
                    'id'           => $p->inReplyToEmail->id,
                    'thread_id'    => $p->inReplyToEmail->thread_id,
                    'subject'      => $p->inReplyToEmail->subject,
                    'from_label'   => $p->inReplyToEmail->from_label,
                    'from_address' => $p->inReplyToEmail->from_address,
                    'plain_body'   => \Illuminate\Support\Str::limit($p->inReplyToEmail->plain_body, 1000),
                    'received_at'  => $p->inReplyToEmail->received_at?->format('Y/m/d H:i'),
                ] : null,
            ]);

        return response()->json($pending);
    }

    public function approve(PendingEmail $pending, EmailFetchService $fetchService): JsonResponse
    {
        if ($pending->status !== PendingEmail::STATUS_PENDING) {
            return response()->json(['status' => 'error', 'message' => 'このメールは既に処理済みです'], 422);
        }

        $settings = MailSetting::getSettings();
        $this->applySmtpConfig($settings);

        try {
            DB::transaction(function () use ($pending, $settings, $fetchService) {
                Mail::send([], [], function ($message) use ($pending, $settings) {
                    $message
                        ->to($pending->to_address)
                        ->from($settings->smtp_from_address, $settings->smtp_from_name)
                        ->subject($pending->subject)
                        ->text($pending->body);

                    if ($pending->cc) {
                        $message->cc(array_map('trim', explode(',', $pending->cc)));
                    }

                    if ($pending->bcc) {
                        $message->bcc(array_map('trim', explode(',', $pending->bcc)));
                    }

                    if ($pending->reply_type !== PendingEmail::TYPE_COMPOSE && $pending->inReplyToEmail) {
                        $msgId = $pending->inReplyToEmail->message_id;
                        if ($msgId) {
                            $message->getHeaders()
                                ->addTextHeader('In-Reply-To', $msgId)
                                ->addTextHeader('References', $msgId);
                        }
                    }

                    foreach ($pending->attachment_paths ?? [] as $att) {
                        $fullPath = Storage::disk('local')->path($att['path']);
                        if (file_exists($fullPath)) {
                            $message->attach($fullPath, [
                                'as'   => $att['filename'],
                                'mime' => $att['mime_type'],
                            ]);
                        }
                    }
                });

                // 送信済みメールを記録
                $inReplyToId = $pending->inReplyToEmail?->message_id;
                $thread = $fetchService->resolveThread($pending->subject, $inReplyToId, $settings->smtp_from_address);

                $email = Email::create([
                    'thread_id'    => $thread->id,
                    'message_id'   => 'SENT_' . time() . '_' . uniqid(),
                    'in_reply_to'  => $inReplyToId,
                    'subject'      => $pending->subject,
                    'from_address' => $settings->smtp_from_address,
                    'from_name'    => $settings->smtp_from_name,
                    'to_address'   => $pending->to_address,
                    'cc'           => $pending->cc,
                    'bcc'          => $pending->bcc,
                    'body_text'    => $pending->body,
                    'received_at'  => now(),
                ]);

                $thread->update(['last_email_at' => now()]);

                // 添付ファイルを永久保存場所に移動して記録
                foreach ($pending->attachment_paths ?? [] as $att) {
                    $oldPath = $att['path'];
                    $safeName = preg_replace('/[^A-Za-z0-9._\-]/u', '_', $att['filename']);
                    $newPath = "attachments/{$email->id}/{$safeName}";

                    if (Storage::disk('local')->exists($oldPath)) {
                        $content = Storage::disk('local')->get($oldPath);
                        Storage::disk('local')->put($newPath, $content);
                        
                        EmailAttachment::create([
                            'email_id'  => $email->id,
                            'filename'  => $att['filename'],
                            'mime_type' => $att['mime_type'],
                            'size'      => $att['size'],
                            'disk_path' => $newPath,
                        ]);
                    }
                }

                $pending->update([
                    'status'      => PendingEmail::STATUS_APPROVED,
                    'approved_at' => now(),
                ]);
            });

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function reject(PendingEmail $pending): JsonResponse
    {
        if ($pending->status !== PendingEmail::STATUS_PENDING) {
            return response()->json(['status' => 'error', 'message' => 'このメールは既に処理済みです'], 422);
        }

        $pending->update(['status' => PendingEmail::STATUS_REJECTED]);

        return response()->json(['status' => 'ok']);
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

        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
    }
}
