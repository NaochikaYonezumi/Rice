<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(EmailAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->disk_path), 404, '添付ファイルが見つかりません');

        return Storage::disk('local')->download(
            $attachment->disk_path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }

    /**
     * 添付ファイル一覧 (/attachments) から「削除」と称して非表示にする。
     *
     * 重要: 実ファイル / DB レコード / メール本文側の表示には触らない。
     * `hidden_from_list = true` を立てるだけで、添付ファイルメニューからのみ消える。
     * 元メールを開けば添付プレビュー・ダウンロードは引き続き可能。
     */
    public function destroy(EmailAttachment $attachment): JsonResponse
    {
        try {
            $attachment->update(['hidden_from_list' => true]);
        } catch (\Throwable $e) {
            Log::warning('AttachmentController.destroy: hide failed', [
                'id'    => $attachment->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error', 'message' => '非表示にできませんでした'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * 添付ファイル単体アップロード。
     * - thread_id 指定時: そのスレッドに紐付く合成 Email を作って添付
     * - thread_id 未指定時: 新規スレッド+合成 Email を作って添付
     * - chat_room_id 指定時: 生成した (または既存の) スレッドをそのルームにバンドルする。
     *   これにより 添付一覧の「ルーム絞り込み」で、他ルームからは見えない状態になる。
     * いずれもメール送信は行わず、添付管理画面に出すための「マニュアル登録」扱い。
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files'         => 'required|array|min:1',
            'files.*'       => 'file|max:51200', // 50 MB
            'thread_id'     => 'nullable|integer|exists:email_threads,id',
            'chat_room_id'  => 'nullable|integer|exists:chat_rooms,id',
        ]);

        $user = Auth::user();
        $userEmail = $user?->email ?? 'manual-upload@local';
        $userName  = $user?->name  ?? '手動アップロード';

        // ルームの解決 + 可視性チェック
        $room = null;
        if (!empty($validated['chat_room_id'])) {
            $room = \App\Models\ChatRoom::visibleTo($user?->id)->find($validated['chat_room_id']);
            if (!$room) {
                return response()->json([
                    'status'  => 'error',
                    'message' => '指定ルームにアクセスできません',
                ], 403);
            }
        }

        // スレッドの解決 + ルームバンドル + Email/Attachment 作成を 1 トランザクションで実行。
        // ここでバンドル登録が失敗するとデータベース変更を全てロールバックし、
        // 「ルーム外に漏れた合成スレッド」が残らないことを保証する。
        $files = $request->file('files') ?? [];
        try {
            [$thread, $email, $created] = DB::transaction(function () use ($validated, $room, $userEmail, $userName, $files) {
                // スレッドの解決: 指定があれば既存、なければ新規作成 (件名にルーム名を含めて判別しやすく)
                if (!empty($validated['thread_id'])) {
                    $thread = EmailThread::findOrFail($validated['thread_id']);
                } else {
                    $subject = $room
                        ? '[手動アップロード / ' . $room->name . '] ' . now()->format('Y/m/d H:i')
                        : '[手動アップロード] ' . now()->format('Y/m/d H:i');
                    $thread = EmailThread::create([
                        'subject'          => $subject,
                        'status'           => 'inbox',
                        'last_email_at'    => now(),
                        // メール一覧画面では除外する印
                        // (添付一覧 / ルームのバンドル先には引き続き表示)
                        'is_manual_upload' => true,
                    ]);
                }

                // ルーム指定時: スレッドをルームの bundledThreads に必ず追加。
                // syncWithoutDetaching は他ルームへのバンドルを保ったまま追加するので
                // 既に他ルームに居る既存スレッドにも安全。
                if ($room) {
                    $room->bundledThreads()->syncWithoutDetaching([$thread->id]);
                    // 確実にバンドルされたか検証 (検証失敗ならトランザクションを失敗扱いに)
                    $stillBundled = DB::table('chat_room_thread')
                        ->where('chat_room_id', $room->id)
                        ->where('email_thread_id', $thread->id)
                        ->exists();
                    if (!$stillBundled) {
                        throw new \RuntimeException('Bundle attach verification failed');
                    }
                }

                $email = Email::create([
                    'thread_id'    => $thread->id,
                    'message_id'   => 'MANUAL_' . time() . '_' . uniqid(),
                    'in_reply_to'  => null,
                    'subject'      => $thread->subject,
                    'from_address' => $userEmail,
                    'from_name'    => $userName,
                    'to_address'   => '',
                    'cc'           => null,
                    'body_text'    => ($room ? "ルーム『{$room->name}』に手動アップロードされたファイルです (" : '手動アップロードされたファイルです (')
                                      . count($files) . ' 件)',
                    'body_html'    => '',
                    'received_at'  => now(),
                ]);

                $created = [];
                foreach ($files as $file) {
                    $safeName = preg_replace('/[^A-Za-z0-9._\-]/u', '_', $file->getClientOriginalName() ?: 'upload');
                    $path = "attachments/{$email->id}/{$safeName}";
                    Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

                    $att = EmailAttachment::create([
                        'email_id'  => $email->id,
                        'filename'  => $file->getClientOriginalName() ?: $safeName,
                        'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                        'size'      => $file->getSize() ?: 0,
                        'disk_path' => $path,
                    ]);
                    $created[] = $att->id;
                }

                $thread->update(['last_email_at' => now()]);

                return [$thread, $email, $created];
            });
        } catch (\Throwable $e) {
            Log::warning('AttachmentController.upload: transaction failed', [
                'room_id' => $room?->id,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'アップロードに失敗しました: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'       => 'ok',
            'thread_id'    => $thread->id,
            'email_id'     => $email->id,
            'chat_room_id' => $room?->id,
            'room_name'    => $room?->name,
            'created_ids'  => $created,
            'count'        => count($created),
        ]);
    }

    public function index(Request $request)
    {
        if (!$request->expectsJson()) {
            return view('attachments.index');
        }

        $q          = trim($request->input('q', ''));
        $typeFilter = $request->input('type', '');      // '' | 'image' | 'document' | 'other'
        $direction  = $request->input('direction', ''); // '' | 'received' | 'sent'
        $customerId = $request->input('customer_id');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $sort       = $request->input('sort', 'desc'); // 'asc' | 'desc'
        $chatRoomId = $request->input('chat_room_id'); // ルームに束ねられたスレッドの添付のみ表示
        $threadId   = $request->input('thread_id');    // 指定スレッドの添付のみ表示
        // 個人 / 共有 切替: 添付も紐づくスレッドの owner_user_id で絞り込む.
        $inboxScope = $request->input('scope', 'shared');
        if (!in_array($inboxScope, ['shared', 'personal'], true)) {
            $inboxScope = 'shared';
        }

        // ルームフィルター。値の解釈:
        //   - 'all' / 空 → フィルタなし
        //   - 'none'    → 「どのルームにも紐付いていないスレッド」の添付ファイルだけ
        //   - 数値 ID   → 指定ルームに束ねられたスレッドの添付ファイルだけ
        $roomThreadIds = null;
        $noRoomMode = false;
        if ($chatRoomId === 'none' || $chatRoomId === '__none__') {
            $noRoomMode = true;
        } elseif ($chatRoomId && $chatRoomId !== 'all') {
            $room = \App\Models\ChatRoom::visibleTo(auth()->id())->find($chatRoomId);
            if ($room) {
                // 階層対応: 親ルームを指定された場合は子孫ルームのスレッドも含める.
                $roomIds = $room->descendantRoomIds();
                $roomThreadIds = \Illuminate\Support\Facades\DB::table('chat_room_thread')
                    ->whereIn('chat_room_id', $roomIds)
                    ->pluck('email_thread_id')
                    ->map(fn($i) => (int) $i)
                    ->unique()
                    ->values()
                    ->all();
            } else {
                $roomThreadIds = []; // 不可視/存在しないルーム = 空結果
            }
        }

        $query = EmailAttachment::with('email.thread')
            // 添付一覧 (/attachments) からユーザが「削除 (= 非表示)」したものは除外
            // メール本文側からは引き続き表示される
            ->where(function ($q) {
                $q->where('hidden_from_list', false)
                  ->orWhereNull('hidden_from_list');
            })
            // 個人/共有 切替: 紐づくスレッドの owner_user_id でフィルタ
            ->whereHas('email.thread', function ($q) use ($inboxScope) {
                if ($inboxScope === 'personal') {
                    $q->where('owner_user_id', auth()->id());
                } else {
                    $q->whereNull('owner_user_id');
                }
            })
            ->when($customerId === 'none', function($query) {
                $query->whereHas('email.thread', fn($q) => $q->whereNull('customer_id'));
            })
            ->when($customerId && $customerId !== 'none', function($query) use ($customerId) {
                $query->whereHas('email.thread', fn($q) => $q->where('customer_id', $customerId));
            })
            ->when($roomThreadIds !== null, function($query) use ($roomThreadIds) {
                $query->whereHas('email', fn($e) => $e->whereIn('thread_id', $roomThreadIds ?: [0]));
            })
            ->when($noRoomMode, function($query) {
                // どのルームにも紐付いていない (= chat_room_thread に thread_id が無い) スレッドの添付だけ
                $query->whereHas('email', function ($e) {
                    $e->whereNotIn('thread_id', \Illuminate\Support\Facades\DB::table('chat_room_thread')
                        ->select('email_thread_id'));
                });
            })
            ->when($threadId, function($query) use ($threadId) {
                // スレッド絞り込み (ルーム絞り込みと併用可能 - AND)
                $query->whereHas('email', fn($e) => $e->where('thread_id', $threadId));
            })
            ->when($q, function($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('filename', 'like', "%{$q}%")
                        ->orWhereHas('email', fn($e) => $e->where('subject', 'like', "%{$q}%"));
                });
            })
            // 受信/送信フィルタ (Email::message_id が SENT_ で始まるものは送信)
            ->when($direction === 'sent', fn($query) =>
                $query->whereHas('email', fn($e) => $e->where('message_id', 'like', 'SENT\_%')))
            ->when($direction === 'received', fn($query) =>
                $query->whereHas('email', fn($e) => $e->where(function ($q) {
                    $q->whereNull('message_id')
                      ->orWhere('message_id', 'not like', 'SENT\_%');
                })))
            ->when($dateFrom, fn($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($typeFilter === 'image',    fn($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($typeFilter === 'document', fn($q) => $q->where(function ($q) {
                $q->where('mime_type', 'like', 'application/pdf')
                  ->orWhere('mime_type', 'like', 'application/msword')
                  ->orWhere('mime_type', 'like', 'application/vnd.openxmlformats%')
                  ->orWhere('mime_type', 'like', 'application/vnd.ms-%')
                  ->orWhere('mime_type', 'like', 'text/%');
            }))
            ->when($typeFilter === 'other',    fn($q) => $q->where('mime_type', 'not like', 'image/%')
                ->where('mime_type', 'not like', 'application/pdf')
                ->where('mime_type', 'not like', 'application/msword')
                ->where('mime_type', 'not like', 'application/vnd.openxmlformats%')
                ->where('mime_type', 'not like', 'application/vnd.ms-%')
                ->where('mime_type', 'not like', 'text/%'))
            ->orderBy('created_at', $sort === 'asc' ? 'asc' : 'desc');

        $perPage = max(10, min(100, (int) $request->input('per_page', 30)));
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $query->count();
        $totalPages = (int) max(1, ceil($total / $perPage));
        // 範囲外の page はクランプ
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $attachments = $query->skip($offset)->take($perPage)->get()->map(function ($a) {
            $isSent = $a->email && is_string($a->email->message_id) && str_starts_with($a->email->message_id, 'SENT_');
            return [
                'id'            => $a->id,
                'filename'      => $a->filename,
                'mime_type'     => $a->mime_type,
                'size'          => $a->humanSize(),
                'size_bytes'    => $a->size,
                'is_image'      => $a->isImage(),
                'url'           => route('attachments.download', $a->id),
                'email_id'      => $a->email_id,
                'email_subject' => $a->email?->subject ?? '(削除済み)',
                'from_label'    => $a->email?->from_label ?? '—',
                // メール一覧 UI と同じく「送信者はメールアドレスを主表示」する方針のため
                // from_address / cc も払い出す。null/欠落時は空文字でフロントが扱いやすいよう '' に正規化。
                'from_address'  => $a->email?->from_address ?? '',
                'to_address'    => $a->email?->to_address ?? '—',
                'cc'            => $a->email?->cc ?? '',
                'received_at'   => $a->email?->received_at?->format('Y/m/d H:i') ?? '—',
                'thread_id'     => $a->email?->thread_id,
                'direction'     => $isSent ? 'sent' : 'received',
            ];
        });

        return response()->json([
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'attachments' => $attachments,
        ]);
    }
}
