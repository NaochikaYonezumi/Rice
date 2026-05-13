<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
// EmailThread は ticket helper のために明示参照 (重複 use 防止)
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

        // 自分が承認者として指定された案件のみ表示するフィルタ
        if ($request->boolean('for_me')) {
            $query->where('target_approver_user_id', auth()->id());
        }

        // 自分が依頼者 (作成者) の案件のみ表示するフィルタ
        if ($request->boolean('mine')) {
            $query->where('created_by_user_id', auth()->id());
        }

        if ($request->has('customer_id')) {
            $query->whereHas('inReplyToEmail.thread', function($q) use ($request) {
                if ($request->customer_id === 'none') {
                    $q->whereNull('customer_id');
                } else {
                    $q->where('customer_id', $request->customer_id);
                }
            });
        }

        $pending = $query->with(['inReplyToEmail.thread', 'creator', 'targetApprover', 'rejecter', 'approver'])
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
                'target_approver_user_id' => $p->target_approver_user_id,
                'target_approver_name'    => $p->targetApprover?->name,
                'rejection_reason'        => $p->rejection_reason,
                'rejected_at'             => $p->rejected_at?->format('Y/m/d H:i'),
                'rejected_by_name'        => $p->rejecter?->name,
                'approved_at'             => $p->approved_at?->format('Y/m/d H:i'),
                'approved_by_name'        => $p->approver?->name,
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

        // 承認者が指定されている場合、その人以外は承認不可
        if ($pending->target_approver_user_id && $pending->target_approver_user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'この承認依頼は他のユーザーが承認者として指定されています。',
            ], 403);
        }

        // 承認依頼をした場合に自分は承認できない制限
        if ($pending->created_by_user_id === auth()->id()) {
            return response()->json(['status' => 'error', 'message' => '自分が作成したメールを自分で承認することはできません。'], 403);
        }

        $settings = MailSetting::getSettings();
        $this->applySmtpConfig($settings);

        try {
            DB::transaction(function () use ($pending, $settings, $fetchService) {
                // (1) 先にスレッドを解決してチケット番号を確定させる
                $inReplyToId = $pending->inReplyToEmail?->message_id;
                $fromAddress = $pending->from_address ?: $settings->smtp_from_address;
                $thread = $fetchService->resolveThread($pending->subject, $inReplyToId, $fromAddress);
                $ticket = $thread->ensureTicketNumber();

                // (2) 件名にチケット番号を埋め込む (受信時にスレッド復元するための鍵)
                $sendSubject = EmailThread::ensureTicketInSubject($pending->subject, $ticket);

                Mail::send([], [], function ($message) use ($pending, $settings, $sendSubject, $fromAddress) {
                    $message
                        ->to($pending->to_address)
                        ->from($fromAddress, $settings->smtp_from_name)
                        ->subject($sendSubject)
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

                // 送信済みメールを記録 (スレッドは上で解決済み)
                $email = Email::create([
                    'thread_id'    => $thread->id,
                    'message_id'   => 'SENT_' . time() . '_' . uniqid(),
                    'in_reply_to'  => $inReplyToId,
                    'subject'      => $sendSubject,
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

            // Phase 6-3: SMTP 送信成功後に AI 採用判定 (ai_log_id が紐付いていれば)
            try {
                app(\Modules\AIReply\Services\AdoptionEvaluator::class)->evaluate($pending->refresh());
            } catch (\Throwable $e) {
                \Log::warning('AdoptionEvaluator failed', [
                    'pending_id' => $pending->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 自分が出した承認依頼を取り下げる (下書きに戻す)
     */
    public function withdraw(PendingEmail $pending): JsonResponse
    {
        if ($pending->status !== PendingEmail::STATUS_PENDING) {
            return response()->json([
                'status'  => 'error',
                'message' => 'この依頼は既に処理済みのため取り下げできません。',
            ], 422);
        }

        // 依頼者のみ取り下げ可能
        if ($pending->created_by_user_id !== auth()->id()) {
            return response()->json([
                'status'  => 'error',
                'message' => '自分が出した依頼のみ取り下げできます。',
            ], 403);
        }

        $pending->update([
            'status'                  => PendingEmail::STATUS_DRAFT,
            'target_approver_user_id' => null,
            // 取り下げ履歴をメモに追記
            'memo' => trim((string) $pending->memo) === ''
                ? '【取り下げ】 ' . now()->format('Y/m/d H:i')
                : '【取り下げ】 ' . now()->format('Y/m/d H:i') . "\n— 元のメモ —\n" . $pending->memo,
        ]);

        return response()->json([
            'status'  => 'ok',
            'message' => '依頼を取り下げ、下書きに戻しました。',
        ]);
    }

    public function reject(Request $request, PendingEmail $pending): JsonResponse
    {
        if ($pending->status !== PendingEmail::STATUS_PENDING) {
            return response()->json(['status' => 'error', 'message' => 'このメールは既に処理済みです'], 422);
        }

        // 承認者が指定されている場合、その人以外は却下不可 (D)
        if ($pending->target_approver_user_id && $pending->target_approver_user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'この承認依頼は他のユーザーが承認者として指定されています。',
            ], 403);
        }

        // 自身の依頼は却下できない
        if ($pending->created_by_user_id === auth()->id()) {
            return response()->json(['status' => 'error', 'message' => '自分が作成したメールを自分で却下することはできません。'], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:1|max:2000',
        ], [
            'rejection_reason.required' => '却下理由を入力してください。',
            'rejection_reason.min'      => '却下理由を入力してください。',
            'rejection_reason.max'      => '却下理由は 2000 文字以内で入力してください。',
        ]);
        $reason = trim($validated['rejection_reason']);
        if ($reason === '') {
            return response()->json([
                'status'  => 'error',
                'message' => '却下理由を入力してください。',
                'errors'  => ['rejection_reason' => ['却下理由を入力してください。']],
            ], 422);
        }
        $rejecterName = auth()->user()->name ?? '承認者';

        \Illuminate\Support\Facades\DB::transaction(function () use ($pending, $reason, $rejecterName) {
            // (B) 却下された内容を「下書き」として再生成 → 依頼者が再編集できる
            //     却下情報 (rejected_by/at/reason) は下書きにも保持し、UI でバナー表示する
            $memoLines = [];
            $memoLines[] = '【却下されました】 by ' . $rejecterName . ' (' . now()->format('Y/m/d H:i') . ')';
            if ($reason !== '') {
                $memoLines[] = '理由: ' . $reason;
            }
            if ($pending->memo) {
                $memoLines[] = '— 元のメモ —';
                $memoLines[] = $pending->memo;
            }

            $newDraft = $pending->replicate(['status', 'approved_at', 'approved_by_user_id']);
            $newDraft->status                   = PendingEmail::STATUS_DRAFT;
            $newDraft->memo                     = implode("\n", $memoLines);
            $newDraft->target_approver_user_id  = null;   // 再選択させる
            $newDraft->approved_at              = null;
            $newDraft->approved_by_user_id      = null;
            // 却下情報は構造化して新ドラフトにも保持 (UI 表示用)
            $newDraft->rejected_by_user_id      = auth()->id();
            $newDraft->rejected_at              = now();
            $newDraft->rejection_reason         = $reason !== '' ? $reason : null;
            // 元レコードは削除するため source_rejected_id は不要 (リファレンスを残さない)
            $newDraft->source_rejected_id       = null;
            $newDraft->save();

            // (A) 却下と同時に下書きとして再生成されたため、元の承認依頼レコードは削除
            //     → 却下済一覧 (status=rejected) には残さず、下書き一覧で再編集できる状態にする
            $pending->delete();
        });

        // (C) 依頼者へ通知
        if ($pending->created_by_user_id) {
            $creator = \App\Models\User::find($pending->created_by_user_id);
            if ($creator) {
                $creator->notify(new \App\Notifications\RejectedNotification($pending, $reason !== '' ? $reason : null, $rejecterName));
            }
        }

        return response()->json([
            'status'  => 'ok',
            'message' => '却下しました。下書きとして再編集可能になっています。',
        ]);
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
