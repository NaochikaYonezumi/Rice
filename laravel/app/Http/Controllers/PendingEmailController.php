<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\MailSetting;
use App\Models\PendingEmail;
use Modules\MailClient\Services\EmailFetcher;
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

        $pending = $query->with(['inReplyToEmail.thread', 'creator'])
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
                'created_by'       => $p->creator?->name ?? $p->created_by,
                'created_by_user_id' => $p->created_by_user_id,
                'memo'             => $p->memo,
                'attachments'      => collect($p->attachment_paths ?? [])->map(function ($a) {
                    // 旧形式 (path文字列) と新形式 (連想配列) の両対応
                    if (is_string($a)) {
                        $path = $a;
                        $filename = basename($a);
                        $bytes = Storage::disk('private')->exists($path) ? Storage::disk('private')->size($path) : 0;
                    } else {
                        $path = $a['path'] ?? '';
                        $filename = $a['filename'] ?? basename($path);
                        $bytes = $a['size']
                            ?? (Storage::disk('private')->exists($path) ? Storage::disk('private')->size($path) : 0);
                    }
                    return [
                        'filename' => $filename,
                        'size'     => $this->humanSize((int) $bytes),
                    ];
                })->values(),
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

    public function approve(PendingEmail $pending, EmailFetcher $fetchService): JsonResponse
    {
        if ($pending->status !== PendingEmail::STATUS_PENDING) {
            return response()->json(['status' => 'error', 'message' => 'このメールは既に処理済みです'], 422);
        }

        // 承認依頼をした場合に自分は承認できない制限
        if ($pending->created_by_user_id === auth()->id()) {
            return response()->json(['status' => 'error', 'message' => '自分が作成したメールを自分で承認することはできません。'], 403);
        }

        $settings = MailSetting::getSettings();
        $this->applySmtpConfig($settings);

        try {
            DB::transaction(function () use ($pending, $settings, $fetchService) {
                Mail::send([], [], function ($message) use ($pending, $settings) {
                    $fromAddress = $pending->from_address ?: $settings->smtp_from_address;
                    $message
                        ->to($pending->to_address)
                        ->from($fromAddress, $settings->smtp_from_name)
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
                        $info = $this->normalizeAttachment($att);
                        if ($info && Storage::disk('private')->exists($info['path'])) {
                            $message->attach(Storage::disk('private')->path($info['path']), [
                                'as'   => $info['filename'],
                                'mime' => $info['mime_type'],
                            ]);
                        }
                    }
                });

                // 送信済みメールを記録
                $inReplyToId = $pending->inReplyToEmail?->message_id;
                $fromAddress = $pending->from_address ?: $settings->smtp_from_address;
                $thread = $fetchService->resolveThread($pending->subject, $inReplyToId, $fromAddress);

                $email = Email::create([
                    'thread_id'    => $thread->id,
                    'message_id'   => 'SENT_' . time() . '_' . uniqid(),
                    'in_reply_to'  => $inReplyToId,
                    'subject'      => $pending->subject,
                    'from_address' => $fromAddress,
                    'from_name'    => $settings->smtp_from_name,
                    'to_address'   => $pending->to_address,
                    'cc'           => $pending->cc,
                    'bcc'          => $pending->bcc,
                    'body_text'    => $pending->body,
                    'received_at'  => now(),
                ]);

                $thread->update(['last_email_at' => now()]);

                // 添付ファイルを永久保存場所に移動して記録 (送信済みディレクトリ)
                foreach ($pending->attachment_paths ?? [] as $att) {
                    $info = $this->normalizeAttachment($att);
                    if (!$info || !Storage::disk('private')->exists($info['path'])) {
                        continue;
                    }
                    $safeName = preg_replace('/[^A-Za-z0-9._\-]/u', '_', $info['filename']);
                    $newPath  = "attachments/{$email->id}/{$safeName}";

                    Storage::disk('local')->put($newPath, Storage::disk('private')->get($info['path']));

                    EmailAttachment::create([
                        'email_id'  => $email->id,
                        'filename'  => $info['filename'],
                        'mime_type' => $info['mime_type'],
                        'size'      => $info['size'],
                        'disk_path' => $newPath,
                    ]);
                }

                $pending->update([
                    'status'               => PendingEmail::STATUS_APPROVED,
                    'approved_at'          => now(),
                    'approved_by_user_id' => auth()->id(),
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

        $pending->update([
            'status' => PendingEmail::STATUS_REJECTED,
            'rejected_by_user_id' => auth()->id(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * 添付エントリを {path, filename, mime_type, size} 連想配列に正規化する。
     * 旧データ (path 文字列) も読めるよう両対応。
     */
    private function normalizeAttachment($att): ?array
    {
        if (is_string($att)) {
            if ($att === '') return null;
            $size = Storage::disk('private')->exists($att) ? Storage::disk('private')->size($att) : 0;
            return [
                'path'      => $att,
                'filename'  => basename($att),
                'mime_type' => Storage::disk('private')->exists($att)
                    ? (Storage::disk('private')->mimeType($att) ?: 'application/octet-stream')
                    : 'application/octet-stream',
                'size'      => $size,
            ];
        }
        if (!is_array($att) || empty($att['path'])) return null;
        return [
            'path'      => $att['path'],
            'filename'  => $att['filename']  ?? basename($att['path']),
            'mime_type' => $att['mime_type'] ?? 'application/octet-stream',
            'size'      => (int) ($att['size'] ?? 0),
        ];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
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
