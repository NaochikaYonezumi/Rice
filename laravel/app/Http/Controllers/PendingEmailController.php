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
        $uid   = auth()->id();

        // ===== 「あなた宛」フィルタ =====
        // タブ (status) によって意味を変える:
        //   - pending  : 自分が承認者に指定された (or 承認者未指定で誰でも承認可) 案件
        //                → まだアクション前なので「指定された」が「あなた宛」と等価
        //   - approved : 自分が実際に承認した案件 (approved_by_user_id = 自分)
        //   - rejected : 自分が実際に却下した案件 (rejected_by_user_id = 自分)
        // 旧実装は全タブ一律 target_approver_user_id で絞っていたため、
        // 「target に指定されていないが自分が結果的に承認した」案件が漏れたり、
        // 逆に「target だったが他人が承認した」案件が「あなた宛 送信済」に紛れていた。
        if ($request->boolean('for_me')) {
            if ($status === PendingEmail::STATUS_APPROVED) {
                $query->where('approved_by_user_id', $uid);
            } elseif ($status === PendingEmail::STATUS_REJECTED) {
                $query->where('rejected_by_user_id', $uid);
            } elseif ($status === PendingEmail::STATUS_SCHEDULED) {
                // 予約中: 自分が予約者 (作成者本人 or 承認時に予約に切替えた承認者) のもの
                $query->where(function ($q) use ($uid) {
                    $q->where('created_by_user_id', $uid)
                      ->orWhere('approved_by_user_id', $uid);
                });
            } else {
                // pending: 承認者指定が自分 or 未指定 (誰でも承認可) のもの
                $query->where(function ($q) use ($uid) {
                    $q->where('target_approver_user_id', $uid)
                      ->orWhereNull('target_approver_user_id');
                });
            }
        }

        // ===== 「自分が依頼」フィルタ =====
        // 全タブ共通で「自分が作成した = 他のユーザーに承認を依頼した」案件のみ。
        if ($request->boolean('mine')) {
            $query->where('created_by_user_id', $uid);
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
                // フロントの行内バッジ (送信済/承認待ち/却下) や削除権限判定で使う
                'status'           => $p->status,
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
                // 履歴削除権限の判定をフロントで行うため、却下を実行したユーザの id も返す
                'rejected_by_user_id'     => $p->rejected_by_user_id,
                'approved_at'             => $p->approved_at?->format('Y/m/d H:i'),
                'approved_by_name'        => $p->approver?->name,
                // 自己送信 (作成者本人が「今すぐ送信」した) か承認経由 (他のユーザが承認した) かの判別.
                // approved_by_user_id === created_by_user_id なら自己送信.
                // approved_by_name の表示や承認バナーの文言切り替えに使う.
                'approved_by_user_id'     => $p->approved_by_user_id,
                'is_self_sent'            => $p->approved_by_user_id !== null
                                              && $p->approved_by_user_id === $p->created_by_user_id,
                // 予約送信ヒント / 状態 (承認 UI で「希望: 5/23 10:00」「予約中」を表示するため).
                // DB は UTC で保持、表示は Asia/Tokyo に変換 (日本ユーザ向け).
                'scheduled_for'           => $p->scheduled_for?->toIso8601String(),
                'scheduled_for_label'     => $p->scheduled_for?->copy()->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'send_attempts'           => (int) ($p->send_attempts ?? 0),
                'last_send_error'         => $p->last_send_error,
                'memo'             => $p->memo,
                'attachments'      => collect($p->attachment_paths ?? [])->map(function ($a, $i) use ($p) {
                    // 旧形式 (path文字列) と新形式 (連想配列) の両対応
                    if (is_string($a)) {
                        $path = $a;
                        $filename = basename($a);
                        $bytes = Storage::disk('private')->exists($path) ? Storage::disk('private')->size($path) : 0;
                        $mime = Storage::disk('private')->exists($path)
                            ? (Storage::disk('private')->mimeType($path) ?: 'application/octet-stream')
                            : 'application/octet-stream';
                    } else {
                        $path = $a['path'] ?? '';
                        $filename = $a['filename'] ?? basename($path);
                        $bytes = $a['size']
                            ?? (Storage::disk('private')->exists($path) ? Storage::disk('private')->size($path) : 0);
                        $mime = $a['mime_type'] ?? 'application/octet-stream';
                    }
                    return [
                        'index'     => $i,
                        'filename'  => $filename,
                        'size'      => $this->humanSize((int) $bytes),
                        'mime_type' => $mime,
                        'download_url' => route('pending.attachment.download', ['pending' => $p->id, 'index' => $i]),
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

    /**
     * 却下済の依頼を「履歴から削除」する。
     *
     * 承認待ちや送信済は削除させない (取り下げ / 監査履歴のため残す)。
     * 削除権限:
     *   - 元の依頼者 (created_by_user_id) 本人
     *   - 却下を実行した承認者 (rejected_by_user_id) 本人
     *   いずれかなら OK。第三者が他人の却下履歴を勝手に消せないようにする。
     */
    public function destroy(PendingEmail $pending): JsonResponse
    {
        if ($pending->status !== PendingEmail::STATUS_REJECTED) {
            return response()->json([
                'status' => 'error',
                'message' => '却下済の依頼のみ削除できます',
            ], 422);
        }
        $uid = auth()->id();
        $canDelete = ($pending->created_by_user_id === $uid)
            || (isset($pending->rejected_by_user_id) && $pending->rejected_by_user_id === $uid);
        if (!$canDelete) {
            return response()->json([
                'status' => 'error',
                'message' => 'この却下履歴を削除する権限がありません',
            ], 403);
        }
        $pending->delete();
        return response()->json(['status' => 'ok']);
    }

    public function approve(Request $request, PendingEmail $pending, EmailFetcher $fetchService): JsonResponse
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

        // ★ 仕様 (2026-05): 承認依頼には作成者の予約希望は乗らない. 送信タイミングは承認者が決定.
        //   - mode=immediate              : 今すぐ送信
        //   - mode=scheduled + scheduled_for : 承認者が指定した日時で予約 (必須)
        //   - 未指定                      : 即時送信 (デフォルト)
        $data = $request->validate([
            'mode'           => 'nullable|in:immediate,scheduled',
            'scheduled_for'  => 'nullable|date',
            // 旧フィールド名との互換 (フロント実装が混在しても通るように両対応)
            'override_scheduled_for' => 'nullable|date',
        ]);
        $mode = $data['mode'] ?? 'immediate';
        $approverScheduledFor = $data['scheduled_for'] ?? $data['override_scheduled_for'] ?? null;

        $shouldSchedule = false;
        if ($mode === 'scheduled') {
            if (empty($approverScheduledFor)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => '予約送信を選んだ場合は送信日時を指定してください.',
                ], 422);
            }
            try {
                // 承認者の入力 (datetime-local, JST) を UTC で保存. cron は UTC 比較.
                $w = \Carbon\Carbon::parse($approverScheduledFor, 'Asia/Tokyo')->utc();
            } catch (\Throwable) {
                return response()->json([
                    'status'  => 'error',
                    'message' => '送信日時の形式が不正です.',
                ], 422);
            }
            if ($w->lte(now())) {
                return response()->json([
                    'status'  => 'error',
                    'message' => '送信日時は現在以降を指定してください.',
                ], 422);
            }
            $pending->scheduled_for = $w;
            $pending->save();
            $shouldSchedule = true;
        }

        if ($shouldSchedule) {
            $pending->update([
                'status'              => PendingEmail::STATUS_SCHEDULED,
                'approved_at'         => now(),
                'approved_by_user_id' => auth()->id(),
                'send_attempts'       => 0,
                'last_send_error'     => null,
            ]);
            // スレッド状態を再計算 (この pending が pending タブから外れる).
            PendingEmail::syncThreadStatus($pending->inReplyToEmail?->thread_id);
            return response()->json([
                'status'        => 'ok',
                'mode'          => 'scheduled',
                'scheduled_for' => $pending->scheduled_for?->toIso8601String(),
            ]);
        }

        // 即時送信パス
        $settings = MailSetting::getSettings();
        $this->applySmtpConfig($settings);

        try {
            DB::transaction(function () use ($pending, $settings, $fetchService) {
                // (1) 先にスレッドを解決する (内部の chat / 添付 / バンドル機能のためチケット番号自体は維持)
                $inReplyToId = $pending->inReplyToEmail?->message_id;
                $fromAddress = $pending->from_address ?: $settings->smtp_from_address;
                $thread = $fetchService->resolveThread($pending->subject, $inReplyToId, $fromAddress);
                $thread->ensureTicketNumber();  // DB 上の管理用に番号は確保しておく

                // (2) 送信件名はユーザが入力したものをそのまま使う (チケット番号タグは付与しない).
                //   要望: 「件名のチケットだけど やっぱりいれないで」
                //   受信時のスレッド復元は In-Reply-To / References / 件名類似で行うので
                //   タグが無くても多くのケースで同一スレッドへ吸い込める。
                $sendSubject = $pending->subject;

                // 送信本文を組み立てる:
                //  - body (プレーン): 互換用。HTML パートをサポートしないクライアントへのフォールバック。
                //  - body_html: リッチエディタが出した HTML。 sanitize 済み。
                //  - body_html が無い (旧下書き等) ときは plain → <pre> 包みで HTML を合成し、
                //    multipart 送信を常に有効化する (受信側が HTML を期待する環境を考慮)。
                $plainBody = (string) ($pending->body ?? '');
                $htmlBody  = (string) ($pending->body_html ?? '');
                if ($htmlBody === '' && $plainBody !== '') {
                    // 改行を維持しつつ HTML エスケープして <pre style="white-space:pre-wrap">
                    $htmlBody = '<pre style="font-family:inherit;white-space:pre-wrap;margin:0;">'
                              . e($plainBody) . '</pre>';
                }

                Mail::send([], [], function ($message) use ($pending, $settings, $sendSubject, $fromAddress, $plainBody, $htmlBody) {
                    $message
                        ->to($pending->to_address)
                        ->from($fromAddress, $settings->smtp_from_name)
                        ->subject($sendSubject);
                    // text/plain と text/html を multipart で送る (両対応)。
                    // Laravel 11 の Illuminate\Mail\Message::html() / text() は Symfony Mime::Email を経由して
                    // text + html の両 alternative パートを 1 つの multipart/alternative にまとめてくれる。
                    if ($plainBody !== '') {
                        $message->text($plainBody);
                    }
                    if ($htmlBody !== '') {
                        $message->html($htmlBody);
                    }

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
                // body_html は送信時に組み立てた値 ($htmlBody) をそのまま記録する (sanitize 済み)。
                // これでスレッド詳細パネルで「送信済みメールも HTML レンダリング」が効くようになる。
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
                    'body_text'    => $plainBody,
                    'body_html'    => $htmlBody !== '' ? $htmlBody : null,
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

            // 承認 → 送信完了したので、このスレッドに残る他の承認待ちがなければステータスを inbox に戻す
            PendingEmail::syncThreadStatus($pending->inReplyToEmail?->thread_id);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 作成者本人による送信 (Self-Send).
     * 承認フローを経由せず、作成者が自分で「今すぐ送信」または「予約送信」する.
     *
     *   POST /pending-emails/{id}/self-send
     *     body: { scheduled_for?: ISO datetime }
     *
     * ガード:
     *   - 管理者ポリシーが SEND_POLICY_APPROVAL_REQUIRED の場合は拒否 (403).
     *   - 作成者本人のみ実行可能.
     *   - status が draft / scheduled / pending (= 取り下げ前提) の場合のみ実行可.
     *
     * 動作:
     *   - scheduled_for が未来 → status=scheduled に遷移 (cron で送信される)
     *   - scheduled_for が空/過去 → 即時 executeSend() で送信
     */
    public function selfSend(Request $request, PendingEmail $pending, EmailFetcher $fetchService): JsonResponse
    {
        if ($pending->created_by_user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => '他のユーザの下書きは送信できません.'], 403);
        }
        if (!in_array($pending->status, [
            PendingEmail::STATUS_DRAFT,
            PendingEmail::STATUS_SCHEDULED,
            PendingEmail::STATUS_PENDING, // 既に承認依頼を出していても本人が自己送信に切替えて即送れる
        ], true)) {
            return response()->json(['status' => 'error', 'message' => 'この下書きは送信できる状態ではありません.'], 422);
        }
        if (trim((string) $pending->to_address) === '') {
            return response()->json(['status' => 'error', 'message' => '宛先が空です.'], 422);
        }
        if (trim((string) $pending->subject) === '') {
            return response()->json(['status' => 'error', 'message' => '件名が空です.'], 422);
        }

        $data = $request->validate([
            'scheduled_for' => 'nullable|date',
        ]);

        // 仕様: 「ユーザが予約送信を設定した場合 = ユーザが個別に送信」と扱い、approval_required ポリシーは
        // 適用しない. (cron で送るまでに本人が取り消せるため自己責任と見做せる).
        // 即時送信時のみ approval_required ポリシーを適用する.
        //
        // タイムゾーン: <input type="datetime-local"> はナイーブな現地時刻文字列 ("2026-05-25T12:00").
        // 利用者が日本ユーザのみ前提なので Asia/Tokyo で解釈し、内部は UTC で保持する.
        // (app.timezone=UTC のため、UTC に変換しないと cron 比較 scheduled_for <= now() が 9 時間ずれる)
        $hasFutureSchedule = false;
        if (!empty($data['scheduled_for'])) {
            try {
                $tmp = \Carbon\Carbon::parse($data['scheduled_for'], 'Asia/Tokyo')->utc();
                $hasFutureSchedule = $tmp->gt(now());
            } catch (\Throwable) { /* 過去日時扱い */ }
        }

        $settings = MailSetting::getSettings();
        if (!$hasFutureSchedule && $settings->isApprovalRequired()) {
            return response()->json([
                'status'  => 'error',
                'message' => '管理者の設定により、即時送信は承認が必要です. 「承認を依頼」を使うか、予約送信日時を指定してください.',
            ], 403);
        }

        // scheduled_for 指定があれば予約に. 過去/空なら即時送信.
        if ($hasFutureSchedule) {
            // 利用者入力 (JST) → UTC で DB 保存. cron 側は UTC 比較なのでこれが必須.
            $w = \Carbon\Carbon::parse($data['scheduled_for'], 'Asia/Tokyo')->utc();
            $pending->update([
                'status'          => PendingEmail::STATUS_SCHEDULED,
                'scheduled_for'   => $w,
                'send_attempts'   => 0,
                'last_send_error' => null,
                // 承認は経由しないので approved_at は send 完了時に. ここでは「自己決定」を残す
                'target_approver_user_id' => null,
                // 「自己送信の予約」であることをマーカとして残す (approved_by_user_id == created_by で is_self_sent 判定)
                // 実送信完了時に executeSend 内で approved_by_user_id が更新されるが、
                // ここで先に入れておくと scheduled 一覧で「自分が予約した」と判別できる.
                'approved_by_user_id' => auth()->id(),
            ]);
            // pending タブから外れるのでスレッドを再評価
            PendingEmail::syncThreadStatus($pending->inReplyToEmail?->thread_id);
            return response()->json([
                'status'        => 'ok',
                'mode'          => 'scheduled',
                'scheduled_for' => $w->toIso8601String(),
            ]);
        }

        // 即時送信
        try {
            // approve したのは本人. 承認フローではなく自己送信を示すマーカ.
            $this->executeSend($pending, $fetchService, auth()->id());
            return response()->json(['status' => 'ok', 'mode' => 'immediate']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => '送信に失敗しました: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 予約送信を設定する.
     * - 既存の下書き (status=draft) を「予約送信」に切り替え、scheduled_for に日時を入れる.
     * - cron で動くコマンド mail:send-scheduled が scheduled_for <= now() を pick して送る.
     */
    public function schedule(Request $request, PendingEmail $pending): JsonResponse
    {
        $data = $request->validate([
            'scheduled_for' => ['required', 'date'],
        ]);
        // 作成者本人だけが予約できる (他人の下書きをいじれないように)
        if ($pending->created_by_user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => '他のユーザの下書きは予約できません。'], 403);
        }
        if (!in_array($pending->status, [PendingEmail::STATUS_DRAFT, PendingEmail::STATUS_SCHEDULED], true)) {
            return response()->json(['status' => 'error', 'message' => '下書き / 既存予約のみ予約変更できます。'], 422);
        }
        $when = \Carbon\Carbon::parse($data['scheduled_for']);
        // 過去日時の予約は受け付けない (1分以内のグレースなら許す: clock skew 対策)
        if ($when->lt(now()->subMinute())) {
            return response()->json(['status' => 'error', 'message' => '予約日時は現在以降を指定してください。'], 422);
        }
        // 必須項目チェック (宛先 / 件名 が空のまま予約させない)
        if (trim((string) $pending->to_address) === '') {
            return response()->json(['status' => 'error', 'message' => '宛先が空のため予約できません。'], 422);
        }
        if (trim((string) $pending->subject) === '') {
            return response()->json(['status' => 'error', 'message' => '件名が空のため予約できません。'], 422);
        }

        $pending->update([
            'status'         => PendingEmail::STATUS_SCHEDULED,
            'scheduled_for'  => $when,
            'send_attempts'  => 0,
            'last_send_error'=> null,
        ]);
        return response()->json([
            'status'        => 'ok',
            'pending_id'    => $pending->id,
            'scheduled_for' => $when->toIso8601String(),
        ]);
    }

    /**
     * 予約送信を取り消し、下書きに戻す.
     *
     * 取消できるのは以下のいずれか:
     *   - 作成者本人 (自分が予約した自己送信を取り消したい)
     *   - 承認時に予約に切り替えたユーザ (approved_by_user_id) — 通常は管理者/承認者
     *   - admin ロール (管理者は常に取消可能)
     */
    public function unschedule(PendingEmail $pending): JsonResponse
    {
        if ($pending->status !== PendingEmail::STATUS_SCHEDULED) {
            return response()->json(['status' => 'error', 'message' => 'この下書きは予約状態ではありません。'], 422);
        }
        $uid = auth()->id();
        $user = auth()->user();
        $isCreator = $pending->created_by_user_id === $uid;
        $isApprover = $pending->approved_by_user_id !== null && $pending->approved_by_user_id === $uid;
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        if (!$isCreator && !$isApprover && !$isAdmin) {
            return response()->json(['status' => 'error', 'message' => '予約を取り消す権限がありません。'], 403);
        }
        $pending->update([
            'status'              => PendingEmail::STATUS_DRAFT,
            'scheduled_for'       => null,
            // 承認経由で予約されていた場合、approve 関連フィールドをリセットして下書きに戻す
            'approved_at'         => null,
            'approved_by_user_id' => null,
        ]);
        // pending/approved タブから外れたのでスレッドの承認待ち件数を再計算
        PendingEmail::syncThreadStatus($pending->inReplyToEmail?->thread_id);
        return response()->json(['status' => 'ok']);
    }

    /**
     * PendingEmail の実送信処理 (approve と mail:send-scheduled で共有する).
     *
     *  - DB::transaction で「SMTP 送信 + Email 行作成 + 添付保存 + status=approved」をアトミックに.
     *  - 例外は呼び出し側に投げる (呼び出し側がトーストやログを出す責務).
     *  - approve() からは「承認者」, scheduler コマンドからは「ユーザ自身」が approved_by_user_id になる.
     *
     * @param  PendingEmail $pending      送信対象
     * @param  EmailFetcher $fetchService スレッド解決に使う
     * @param  int|null     $approvedBy   approved_by_user_id に入れる userId (null なら作成者本人)
     * @throws \Throwable
     */
    public function executeSend(PendingEmail $pending, EmailFetcher $fetchService, ?int $approvedBy = null): void
    {
        $settings = MailSetting::getSettings();
        $this->applySmtpConfig($settings);

        DB::transaction(function () use ($pending, $settings, $fetchService, $approvedBy) {
            $inReplyToId = $pending->inReplyToEmail?->message_id;
            $fromAddress = $pending->from_address ?: $settings->smtp_from_address;
            $thread      = $fetchService->resolveThread($pending->subject, $inReplyToId, $fromAddress);
            $thread->ensureTicketNumber();

            $sendSubject = $pending->subject;
            $plainBody = (string) ($pending->body ?? '');
            $htmlBody  = (string) ($pending->body_html ?? '');
            if ($htmlBody === '' && $plainBody !== '') {
                $htmlBody = '<pre style="font-family:inherit;white-space:pre-wrap;margin:0;">'
                          . e($plainBody) . '</pre>';
            }

            Mail::send([], [], function ($message) use ($pending, $settings, $sendSubject, $fromAddress, $plainBody, $htmlBody) {
                $message
                    ->to($pending->to_address)
                    ->from($fromAddress, $settings->smtp_from_name)
                    ->subject($sendSubject);
                if ($plainBody !== '') $message->text($plainBody);
                if ($htmlBody !== '')  $message->html($htmlBody);
                if ($pending->cc)  $message->cc(array_map('trim', explode(',', $pending->cc)));
                if ($pending->bcc) $message->bcc(array_map('trim', explode(',', $pending->bcc)));
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
                'body_text'    => $plainBody,
                'body_html'    => $htmlBody !== '' ? $htmlBody : null,
                'received_at'  => now(),
            ]);
            $thread->update(['last_email_at' => now()]);

            foreach ($pending->attachment_paths ?? [] as $att) {
                $info = $this->normalizeAttachment($att);
                if (!$info || !Storage::disk('private')->exists($info['path'])) continue;
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
                'status'              => PendingEmail::STATUS_APPROVED,
                'approved_at'         => now(),
                'approved_by_user_id' => $approvedBy ?? $pending->created_by_user_id,
                'scheduled_for'       => null,
                'last_send_error'     => null,
            ]);
        });

        // スレッドの承認待ち件数を再計算
        PendingEmail::syncThreadStatus($pending->inReplyToEmail?->thread_id);
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

        // 取り下げ後、このスレッドに残る他の承認待ちがなければステータスを inbox に戻す
        PendingEmail::syncThreadStatus($pending->inReplyToEmail?->thread_id);

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
        $rejecterId   = auth()->id();
        $threadId     = $pending->inReplyToEmail?->thread_id;
        $inReplyEmailId = $pending->in_reply_to_email_id;
        $pendingSubject = $pending->subject;

        \Illuminate\Support\Facades\DB::transaction(function () use ($pending, $reason, $rejecterName, $rejecterId, $threadId, $inReplyEmailId, $pendingSubject) {
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

            // 新ドラフトは replicate に頼らず明示的にフィールドコピーする (本文の取り違え/欠落事故防止).
            // 旧実装は replicate() でカラム全コピーしていたが、JSON 列の共有や body_html の混入で
            // 「本文が壊れる」現象が稀に発生したため、必要なフィールドだけを手で写し取る方式に変更。
            $newDraft = new PendingEmail();
            $newDraft->status                   = PendingEmail::STATUS_DRAFT;
            $newDraft->reply_type               = $pending->reply_type;
            $newDraft->in_reply_to_email_id     = $pending->in_reply_to_email_id;
            $newDraft->from_address             = $pending->from_address;
            $newDraft->to_address               = $pending->to_address;
            $newDraft->cc                       = $pending->cc;
            $newDraft->bcc                      = $pending->bcc;
            $newDraft->subject                  = $pending->subject;
            // ★ 本文は明示的にプレーンの body のみコピーする.
            //   HTML 編集は廃止済みのため body_html は新ドラフトに引き継がない (= null).
            //   こうしておくと再オープン時に textarea が確実に元のプレーン文字列で初期化される.
            $newDraft->body                     = (string) ($pending->body ?? '');
            $newDraft->body_html                = null;
            $newDraft->created_by               = $pending->created_by;
            $newDraft->created_by_user_id       = $pending->created_by_user_id;
            // 添付ファイルパスは JSON. 同じ参照だがファイル実体は private ストレージに置きっぱなしなので問題なし.
            $newDraft->attachment_paths         = $pending->attachment_paths;
            $newDraft->memo                     = implode("\n", $memoLines);
            $newDraft->target_approver_user_id  = null;
            $newDraft->approved_at              = null;
            $newDraft->approved_by_user_id      = null;
            // 却下情報は構造化して新ドラフトにも保持 (UI 表示用)
            $newDraft->rejected_by_user_id      = $rejecterId;
            $newDraft->rejected_at              = now();
            $newDraft->rejection_reason         = $reason !== '' ? $reason : null;
            // 新ドラフトから元 (却下済) レコードへの参照を保存。一覧画面で関連付け表示に使う。
            try {
                $newDraft->source_rejected_id   = $pending->id;
            } catch (\Throwable) { /* カラムが無い古い環境では無視 */ }
            $newDraft->save();

            // (A) 元の承認依頼は **却下済として残す** (status=rejected).
            //     旧実装は ->delete() して 却下済タブに何も出ない状態だったが、
            //     ユーザ要望: 「却下したものが却下済に入ってこない」を解消するため履歴を残す。
            //     下書き一覧でも再編集できる ($newDraft) ので両方表示される。
            $pending->status               = PendingEmail::STATUS_REJECTED;
            $pending->rejected_by_user_id  = $rejecterId;
            $pending->rejected_at          = now();
            $pending->rejection_reason     = $reason !== '' ? $reason : null;
            $pending->save();

            // (E) 却下理由をスレッド内チャットに通常のメッセージとして残す
            //     - in_reply_to が無い (新規メール) 場合はスレッドが無いので何もしない
            //     - in_reply_to_email_id があれば email_id にも紐付けて per-email チャットにも表示
            if ($threadId) {
                $chatLines = ['❌ 承認依頼を却下しました'];
                if ($pendingSubject) {
                    $chatLines[] = '件名: ' . $pendingSubject;
                }
                $chatLines[] = '却下者: ' . $rejecterName;
                $chatLines[] = '理由: ' . ($reason !== '' ? $reason : '(理由なし)');
                \App\Models\ThreadComment::create([
                    'thread_id' => $threadId,
                    'email_id'  => $inReplyEmailId,
                    'user_id'   => $rejecterId,
                    'content'   => implode("\n", $chatLines),
                ]);
            }
        });

        // (C) 依頼者へ通知
        if ($pending->created_by_user_id) {
            $creator = \App\Models\User::find($pending->created_by_user_id);
            if ($creator) {
                $creator->notify(new \App\Notifications\RejectedNotification($pending, $reason !== '' ? $reason : null, $rejecterName));
            }
        }

        // (D) このスレッドに残る他の承認待ちがなければステータスを inbox に戻す
        PendingEmail::syncThreadStatus($threadId);

        return response()->json([
            'status'  => 'ok',
            'message' => '却下しました。下書きとして再編集可能になっています。',
        ]);
    }

    /**
     * 承認待ち添付のダウンロード。
     * - 作成者 / 指定された承認者 / 承認者未定義 のいずれかなら閲覧可能
     */
    public function downloadAttachment(PendingEmail $pending, int $index)
    {
        $userId = auth()->id();
        $assignedTo = $pending->target_approver_user_id;
        $canView = $pending->created_by_user_id === $userId
            || $assignedTo === $userId
            || $assignedTo === null;
        if (!$canView) {
            abort(403, 'この承認依頼の添付ファイルを閲覧する権限がありません。');
        }

        $list = $pending->attachment_paths ?? [];
        if (!isset($list[$index])) {
            abort(404, '添付ファイルが見つかりません。');
        }
        $info = $this->normalizeAttachment($list[$index]);
        if (!$info || !Storage::disk('private')->exists($info['path'])) {
            abort(404, '添付ファイルの実体が見つかりません。');
        }

        return Storage::disk('private')->download(
            $info['path'],
            $info['filename'],
            ['Content-Type' => $info['mime_type']],
        );
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
