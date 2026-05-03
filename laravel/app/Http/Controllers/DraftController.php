<?php

namespace App\Http\Controllers;

use App\Models\PendingEmail;
use App\Models\MailSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DraftController extends Controller
{
    public function index()
    {
        return view('drafts.index');
    }

    public function list(): JsonResponse
    {
        $drafts = PendingEmail::where('status', PendingEmail::STATUS_DRAFT)
            ->where('created_by_user_id', auth()->id())
            ->with(['rejecter', 'inReplyToEmail.thread'])
            ->latest()
            ->get()
            ->map(fn($d) => [
                'id'          => $d->id,
                'subject'     => $d->subject,
                'to_address'  => $d->to_address,
                'cc'          => $d->cc,
                'bcc'         => $d->bcc,
                'body'        => $d->body,
                'body_preview'=> $d->body_preview,
                'memo'        => $d->memo,
                'reply_type'  => $d->reply_type,
                'reply_type_label' => $d->reply_type_label,
                'created_at'  => $d->created_at?->format('Y/m/d H:i'),
                'updated_at'  => $d->updated_at?->format('Y/m/d H:i'),
                // 却下から下書きに戻された場合の情報
                'is_rejected'      => $d->rejection_reason !== null || $d->rejected_at !== null,
                'rejection_reason' => $d->rejection_reason,
                'rejected_at'      => $d->rejected_at?->format('Y/m/d H:i'),
                'rejected_by_name' => $d->rejecter?->name,
                'in_reply_to_email_id' => $d->in_reply_to_email_id,
                'thread_subject'  => $d->inReplyToEmail?->thread?->subject,
                'attachment_count' => is_array($d->attachment_paths) ? count($d->attachment_paths) : 0,
            ]);

        return response()->json($drafts);
    }

    /**
     * 下書きを「承認依頼」として送信 (compose-windowを介さず1クリックで)
     */
    public function submit(Request $request, PendingEmail $draft): JsonResponse
    {
        abort_unless($draft->status === PendingEmail::STATUS_DRAFT, 422, 'Not a draft');
        abort_unless($draft->created_by_user_id === auth()->id(), 403, 'Forbidden');

        // 提出時に却下フラグはクリア
        $draft->update([
            'status'              => PendingEmail::STATUS_PENDING,
            'rejected_by_user_id' => null,
            'rejected_at'         => null,
            'rejection_reason'    => null,
        ]);

        $admins = \App\Models\User::where('role', 'admin')
            ->where('id', '!=', auth()->id())
            ->get();
        \Illuminate\Support\Facades\Notification::send(
            $admins,
            new \App\Notifications\ApprovalRequestedNotification($draft)
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * 下書きを compose-window で開いて編集する (新ウィンドウで)
     */
    public function edit(PendingEmail $draft)
    {
        abort_unless($draft->status === PendingEmail::STATUS_DRAFT, 404);
        abort_unless($draft->created_by_user_id === auth()->id(), 403);

        $settings = MailSetting::getSettings();
        $defaultFrom = $draft->from_address ?: ($settings->smtp_from_address ?? '');

        // 返信元情報があれば取得
        $email = null;
        $thread = null;
        $emails = [];
        $mode = 'compose';
        if ($draft->in_reply_to_email_id) {
            $email = \App\Models\Email::with(['thread.customer', 'thread.assignee', 'attachments'])
                ->find($draft->in_reply_to_email_id);
            if ($email) {
                $mode = $draft->reply_type === 'reply_all' ? 'reply_all' : 'reply';
                $thread = $email->thread;
                $threadIds = $thread ? [$thread->id] : [];
                $emails = !empty($threadIds)
                    ? \App\Models\Email::whereIn('thread_id', $threadIds)
                        ->with('attachments')
                        ->orderBy('received_at', 'desc')
                        ->get()
                        ->map(fn($e) => [
                            'id' => $e->id, 'thread_id' => $e->thread_id, 'subject' => $e->subject,
                            'from_label' => $e->from_label, 'from_address' => $e->from_address,
                            'to_address' => $e->to_address, 'cc' => $e->cc,
                            'plain_body' => $e->plain_body,
                            'received_at' => $e->received_at?->format('Y/m/d H:i'),
                            'attachments' => $e->attachments->map(fn($a) => [
                                'id' => $a->id, 'filename' => $a->filename,
                                'url' => route('attachments.download', $a->id),
                            ])->values(),
                        ])->values()->all()
                    : [];
            }
        }

        // 承認者候補
        $approvers = \App\Models\User::where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role])
            ->all();

        return view('emails.compose-window', [
            'mode'         => $mode,
            'email'        => $email ? [
                'id' => $email->id, 'thread_id' => $email->thread_id, 'subject' => $email->subject,
                'from_label' => $email->from_label, 'from_address' => $email->from_address,
                'to_address' => $email->to_address, 'cc' => $email->cc,
                'plain_body' => $email->plain_body,
                'received_at' => $email->received_at?->format('Y/m/d H:i'),
                'attachments' => $email->attachments->map(fn($a) => [
                    'id' => $a->id, 'filename' => $a->filename,
                    'url' => route('attachments.download', $a->id),
                ])->values(),
            ] : null,
            'thread'       => $thread ? [
                'id' => $thread->id, 'subject' => $thread->subject, 'status' => $thread->status,
                'customer' => $thread->customer ? ['id' => $thread->customer->id, 'name' => $thread->customer->name] : null,
                'assignee' => $thread->assignee ? ['id' => $thread->assignee->id, 'name' => $thread->assignee->name] : null,
            ] : null,
            'emails'       => $emails,
            'defaultFrom'  => $defaultFrom,
            'replyTo'      => $draft->to_address ?: '',
            'replyCc'      => $draft->cc ?: '',
            'replyBcc'     => $draft->bcc ?: '',
            'replySubject' => $draft->subject ?: '',
            'approvers'    => $approvers,
            // ▼ 下書き編集モード用の追加データ
            'draftId'      => $draft->id,
            'draftBody'    => $draft->body ?: '',
            'draftMemo'    => $draft->memo,
            'rejectionInfo'=> $draft->rejection_reason ? [
                'reason'      => $draft->rejection_reason,
                'rejected_at' => $draft->rejected_at?->format('Y/m/d H:i'),
                'rejected_by' => $draft->rejecter?->name,
            ] : null,
        ]);
    }

    public function destroy(PendingEmail $draft): JsonResponse
    {
        abort_unless($draft->status === PendingEmail::STATUS_DRAFT, 422, 'Not a draft');
        abort_unless($draft->created_by_user_id === auth()->id(), 403);
        $draft->delete();
        return response()->json(['status' => 'ok']);
    }
}
