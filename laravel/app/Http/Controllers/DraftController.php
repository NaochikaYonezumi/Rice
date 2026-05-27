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

    public function list(\Illuminate\Http\Request $request): JsonResponse
    {
        // 個人 / 共有 切替: 下書きが「個人スレッド由来」か「共有スレッド由来」かで絞り込む.
        // 判定: inReplyToEmail.thread.owner_user_id が自分なら personal、NULL なら shared.
        // 新規作成 (in_reply_to_email_id が null) は mail_account_id があれば personal、無ければ shared.
        $inboxScope = $request->input('scope', 'shared');
        if (!in_array($inboxScope, ['shared', 'personal'], true)) {
            $inboxScope = 'shared';
        }
        $uid = auth()->id();
        // 下書きと「自分が予約した自己送信」を同一画面で表示する.
        // 仕様: 「ユーザが予約送信を設定した場合 = ユーザが個別に送信」なので
        // 予約状態のものも下書き同様、本人がここから内容を確認/取消できる.
        $drafts = PendingEmail::whereIn('status', [PendingEmail::STATUS_DRAFT, PendingEmail::STATUS_SCHEDULED])
            ->where('created_by_user_id', $uid)
            ->when($inboxScope === 'personal', function ($q) use ($uid) {
                // 個人: 自分の個人アカウント経由、または自分が所有するスレッドに対する返信下書き
                $q->where(function ($q) use ($uid) {
                    $q->whereHas('mailAccount', fn($mq) => $mq->where('user_id', $uid))
                      ->orWhereHas('inReplyToEmail.thread', fn($tq) => $tq->where('owner_user_id', $uid));
                });
            }, function ($q) {
                // 共有: 個人アカウント指定なし AND (返信元無し or 返信元スレッドが共有)
                $q->whereNull('mail_account_id')
                  ->where(function ($q) {
                      $q->whereNull('in_reply_to_email_id')
                        ->orWhereHas('inReplyToEmail.thread', fn($tq) => $tq->whereNull('owner_user_id'));
                  });
            })
            ->with(['rejecter', 'inReplyToEmail.thread'])
            ->latest()
            ->get()
            ->map(fn($d) => [
                'id'          => $d->id,
                'status'      => $d->status, // 'draft' / 'scheduled' をフロントで使う
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
                // 予約送信中の場合の情報 (取消ボタンの出し分けと残り時間表示に使う).
                // DB は UTC で保持、表示は Asia/Tokyo に変換.
                'is_scheduled'         => $d->status === PendingEmail::STATUS_SCHEDULED,
                'scheduled_for'        => $d->scheduled_for?->toIso8601String(),
                'scheduled_for_label'  => $d->scheduled_for?->copy()->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
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
     *
     * 対応 status:
     *   - draft     : 通常の下書き編集
     *   - scheduled : 予約送信中のメールを内容確認・編集する.
     *                 compose-window は draftScheduledFor を受け取り「予約送信」ボタンを出す.
     *                 ※ saveDraftToServer (save_as_draft=1) で保存すると status=draft に戻る仕様なので、
     *                   実質的に「予約取消 → 編集」となる. UI 側でその旨を表示する.
     */
    public function edit(PendingEmail $draft)
    {
        abort_unless(in_array($draft->status, [
            PendingEmail::STATUS_DRAFT,
            PendingEmail::STATUS_SCHEDULED,
        ], true), 404);
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

        // 下書きに紐付く既存添付ファイルの一覧 (compose-window 側で表示・削除制御に使う)
        $draftAttachments = collect((array) $draft->attachment_paths)
            ->map(function ($att) {
                if (is_string($att)) {
                    return ['path' => $att, 'filename' => basename($att), 'size' => 0, 'mime_type' => 'application/octet-stream'];
                }
                if (is_array($att) && isset($att['path'])) {
                    return [
                        'path'      => (string) $att['path'],
                        'filename'  => (string) ($att['filename'] ?? basename($att['path'])),
                        'size'      => (int) ($att['size'] ?? 0),
                        'mime_type' => (string) ($att['mime_type'] ?? 'application/octet-stream'),
                    ];
                }
                return null;
            })
            ->filter()
            ->values()
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
            'draftId'          => $draft->id,
            'draftBody'        => $draft->body ?: '',
            // 旧下書き (body_html 列が無かった時代に保存) は null。エディタ側は body をフォールバックに使う。
            'draftBodyHtml'    => $draft->body_html ?: '',
            // 予約日時は DB (UTC) → JST に変換してから datetime-local 形式で渡す.
            // (compose-window の input[type=datetime-local] はナイーブ現地時刻を期待するため)
            'draftScheduledFor' => $draft->scheduled_for?->copy()->setTimezone('Asia/Tokyo')->format('Y-m-d\TH:i'),
            // 予約中フラグ + 表示用ラベル. compose-window 側で「予約中バナー」を出すために使う.
            'draftIsScheduled'      => $draft->status === PendingEmail::STATUS_SCHEDULED,
            'draftScheduledLabel'   => $draft->scheduled_for?->copy()->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
            'draftMemo'        => $draft->memo,
            'draftAttachments' => $draftAttachments,
            'rejectionInfo'=> $draft->rejection_reason ? [
                'reason'      => $draft->rejection_reason,
                'rejected_at' => $draft->rejected_at?->format('Y/m/d H:i'),
                'rejected_by' => $draft->rejecter?->name,
            ] : null,
            'sendPolicy'   => \App\Models\MailSetting::getSettings()->send_policy ?? 'flexible',
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
