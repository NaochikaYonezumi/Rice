<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\EmailThread;
use App\Models\ThreadComment;
use App\Models\UserChatHide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThreadChatController extends Controller
{
    /**
     * チャット一覧ページ (HTML)
     */
    public function index()
    {
        return view('chats.index');
    }

    /**
     * チャット活動のあるスレッド一覧 (JSON)
     * フィルタ:
     *   ?q=...              件名検索
     *   ?mentioned=1        自分宛メンションを含むスレッドのみ
     *   ?mine=1             自分が発言したスレッドのみ
     */
    public function listThreads(Request $request): JsonResponse
    {
        $q          = trim($request->input('q', ''));
        $mentionedMe = $request->boolean('mentioned');
        $myCommented = $request->boolean('mine');
        $showHidden  = $request->boolean('show_hidden');
        $myName      = auth()->user()->name ?? '';
        $myId        = auth()->id();
        // 個人 / 共有 切替: サイドバーのスレッド一覧も scope で絞り込む
        $inboxScope = $request->input('scope', 'shared');
        if (!in_array($inboxScope, ['shared', 'personal'], true)) {
            $inboxScope = 'shared';
        }
        // type => [id => hidden_at] のマップ。非表示登録後にメール/チャットが来たスレッドは
        // 「自動的に再表示」するため、ID リストだけでなく時刻も保持しておく。
        $hiddenMap   = UserChatHide::getHiddenMapByType((int) $myId);
        $hidden      = [
            UserChatHide::TYPE_ROOM   => array_keys($hiddenMap[UserChatHide::TYPE_ROOM] ?? []),
            UserChatHide::TYPE_THREAD => array_keys($hiddenMap[UserChatHide::TYPE_THREAD] ?? []),
        ];

        // メールが届いたスレッドは全て表示 (チャットコメントが無くても OK)
        // チャット投稿前のスレッドからも新しいチャットを始められるようにする。
        $query = EmailThread::query()
            ->with([
                'threadComments' => fn($cq) => $cq->orderByDesc('created_at')->with('user'),
                'latestEmail',
                'assignee',
                'customer',
            ])
            // 個人/共有 切替で表示範囲を絞る
            ->when($inboxScope === 'personal',
                fn($qq) => $qq->where('owner_user_id', $myId),
                fn($qq) => $qq->whereNull('owner_user_id')
            )
            // ゴミ箱化されたスレッドは通常一覧から除外する.
            // (EmailController::index と同じ規約. スレッド本体の status='trash' か,
            //  最後の生きていたメールが全てゴミ箱化されて destroyEmail が親も trash 化したケース)
            ->where(function ($q) {
                $q->where('status', '!=', EmailThread::STATUS_TRASH)
                  ->orWhereNull('status');
            })
            ->whereNull('trashed_at');

        // 複数アカウント切替プルダウンでの絞り込み (個人モード時のみ意味を持つ)
        // 自分が所有する口座IDのみ通す
        if ($inboxScope === 'personal' && $request->filled('mail_account_id')) {
            $aid = (int) $request->input('mail_account_id');
            if ($aid > 0
                && \App\Models\MailAccount::where('id', $aid)->where('user_id', $myId)->exists()) {
                $query->where('mail_account_id', $aid);
            }
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('subject', 'like', "%{$q}%")
                  ->orWhereHas('threadComments', fn($cq) => $cq->where('content', 'like', "%{$q}%"));
            });
        }

        if ($myCommented) {
            $query->whereHas('threadComments', fn($cq) => $cq->where('user_id', $myId));
        }

        // 「依然として非表示」と判定すべきスレッドを活動時刻ベースで先に算出する。
        // (非表示登録後にメール/チャットが届いていれば再表示扱い)
        $stillHiddenIds = [];
        if (!empty($hiddenMap[UserChatHide::TYPE_THREAD])) {
            $hiddenIds = array_keys($hiddenMap[UserChatHide::TYPE_THREAD]);
            $candidates = EmailThread::whereIn('id', $hiddenIds)
                ->with(['threadComments' => fn($cq) => $cq->orderByDesc('created_at')->limit(1)])
                ->get(['id', 'last_email_at']);
            foreach ($candidates as $t) {
                $hiddenAt = $hiddenMap[UserChatHide::TYPE_THREAD][$t->id] ?? null;
                $latestComment = $t->threadComments->first()?->created_at;
                $latestActivity = $t->last_email_at;
                if ($latestComment && (!$latestActivity || $latestComment > $latestActivity)) {
                    $latestActivity = $latestComment;
                }
                // 非表示登録後に活動があれば再表示扱い
                if ($hiddenAt && $latestActivity && $latestActivity > $hiddenAt) continue;
                $stillHiddenIds[] = $t->id;
            }
        }

        // デフォルトは still hidden のスレッドを一覧から除外する (showHidden 時は除外しない)
        if (!$showHidden && !empty($stillHiddenIds)) {
            $query->whereNotIn('id', $stillHiddenIds);
        }

        $threads = $query->get();

        // ユーザーごとの既読時刻を引く
        $readMap = [];
        try {
            $readMap = \App\Models\UserThreadChatRead::where('user_id', $myId)
                ->whereIn('thread_id', $threads->pluck('id')->all())
                ->pluck('last_read_at', 'thread_id')
                ->all();
        } catch (\Throwable) {}

        // チャットピン留め (per-user)
        $pinnedThreadIds = [];
        $pinnedRoomIds   = [];
        try {
            $pins = \App\Models\UserChatPin::where('user_id', $myId)->get();
            foreach ($pins as $p) {
                if ($p->pinnable_type === 'thread') $pinnedThreadIds[$p->pinnable_id] = true;
                elseif ($p->pinnable_type === 'room') $pinnedRoomIds[$p->pinnable_id] = true;
            }
        } catch (\Throwable) {}

        // メンション検出 (PHP 側で名前マッチ)
        $mentionPattern = $myName !== ''
            ? '/@' . preg_quote($myName, '/') . '(?=[\s\n.,!?。、]|$)/u'
            : null;

        $rows = $threads->map(function ($t) use ($mentionPattern, $myId, $readMap, $pinnedThreadIds) {
            $comments = $t->threadComments;
            $latest   = $comments->first();
            $lastRead = $readMap[$t->id] ?? null;
            $unreadCount = 0;
            $unreadMentionCount = 0;
            $hasMyMention = false;
            foreach ($comments as $c) {
                // メンションフラグ: 全コメント (読み済みも含む) で判定
                if ($mentionPattern && $c->user_id !== $myId
                    && preg_match($mentionPattern, (string) $c->content)) {
                    $hasMyMention = true;
                }
                // 未読カウント: 自分の投稿は対象外
                if ($c->user_id === $myId) continue;
                $isUnread = $lastRead === null || $c->created_at > $lastRead;
                if ($isUnread) {
                    $unreadCount++;
                    if ($mentionPattern && preg_match($mentionPattern, (string) $c->content)) {
                        $unreadMentionCount++;
                    }
                }
            }

            return [
                'id'              => $t->id,
                'subject'         => $t->subject ?: '(無題)',
                'ticket_number'   => $t->ticket_number,
                'status'          => $t->status,
                // is_pinned もユーザ毎 (UserChatPin) ベース. 旧 email_threads.is_pinned は廃止予定.
                'is_pinned'       => isset($pinnedThreadIds[$t->id]),
                'is_pinned_chat'  => isset($pinnedThreadIds[$t->id]),
                'customer_name'   => $t->customer?->name,
                'assignee'        => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
                'comment_count'   => $comments->count(),
                'unread_count'    => $unreadCount,
                // 未読メンションのみのバッジ用
                'mention_count'   => $unreadMentionCount,
                // 自分宛フィルタ用 (読み済みも含めて自分宛があるか)
                'has_my_mention'  => $hasMyMention,
                'last_comment'    => $latest ? [
                    'id'          => $latest->id,
                    'preview'     => Str::limit((string) $latest->content, 80),
                    'author'      => $latest->user?->name ?? 'システム',
                    'is_mine'     => $latest->user_id === $myId,
                    'created_at'  => $latest->created_at?->format('Y/m/d H:i'),
                    'created_iso' => $latest->created_at?->toIso8601String(),
                ] : null,
                'thread_last_email_at'  => $t->last_email_at?->format('Y/m/d H:i'),
                'thread_last_email_iso' => $t->last_email_at?->toIso8601String(),
            ];
        })
        ->when($mentionedMe, fn($c) => $c->filter(fn($r) => $r['has_my_mention']))
        // ピン留めを上に、それ以外は (最新コメント時刻 ?? 最新メール受信時刻) 降順
        // チャット未投稿でもメール到着順で並ぶようにフォールバック
        ->sortBy([
            ['is_pinned_chat', 'desc'],
            function ($a, $b) {
                $ak = $a['last_comment']['created_iso'] ?? $a['thread_last_email_iso'] ?? '';
                $bk = $b['last_comment']['created_iso'] ?? $b['thread_last_email_iso'] ?? '';
                return strcmp($bk, $ak);
            },
        ])
        ->values();

        // 同時にスタンドアロンチャットルーム一覧も返す (統合チャット一覧用)
        try {
            // ルームの最終既読時刻を一括取得
            $roomReadMap = [];
            try {
                $roomReadMap = \App\Models\UserRoomChatRead::where('user_id', $myId)
                    ->pluck('last_read_at', 'chat_room_id')
                    ->all();
            } catch (\Throwable) {}

            $roomsRaw = ChatRoom::visibleTo($myId)
                ->with([
                    'comments' => fn($q) => $q->orderByDesc('created_at')->limit(1)->with('user'),
                    'bundledThreads:id',
                ])
                ->orderByDesc('updated_at')
                ->get();

            // ルームごとの「未対応スレッド件数」を 1 クエリでバッチ集計.
            // 未対応 = status IN (inbox, hold, pending(=承認待ち)).
            //   - merge source 除外 / 孤児除外 / 手動アップロード除外
            //   - 完了 / 対応不要 / 迷惑メール / 却下はバッジに入れない
            // ChatRoomController / ReportService と完全に同じ定義に揃える.
            $unhandledStatuses = [
                \App\Models\EmailThread::STATUS_INBOX,
                \App\Models\EmailThread::STATUS_HOLD,
                \App\Models\EmailThread::STATUS_AWAITING_APPROVAL,
            ];
            $threadToRoomsForEmails = [];
            foreach ($roomsRaw as $r) {
                foreach ($r->bundledThreads->pluck('id')->all() as $tid) {
                    $threadToRoomsForEmails[(int) $tid][] = $r->id;
                }
            }
            // ★ status 別に分類して per-room カウントを返す (バッジ色分け用).
            //   ChatRoomController と同じ仕様で受信=青/保留=琥珀/承認待ち=橙を実現する.
            $threadStatusMap = []; // tid => status
            if (!empty($threadToRoomsForEmails)) {
                $rowsForStatus = \App\Models\EmailThread::query()
                    ->whereIn('id', array_keys($threadToRoomsForEmails))
                    ->whereIn('status', $unhandledStatuses)
                    ->whereNotIn('id', \App\Models\ThreadMerge::select('source_thread_id_original'))
                    ->has('emails')
                    ->where(function ($q) {
                        $q->where('is_manual_upload', false)->orWhereNull('is_manual_upload');
                    })
                    ->get(['id', 'status']);
                foreach ($rowsForStatus as $r) {
                    $threadStatusMap[(int) $r->id] = (string) $r->status;
                }
            }
            $activeThreadIds     = array_keys($threadStatusMap);
            $receivedEmailByRoom = [];
            $inboxEmailByRoom    = [];
            $holdEmailByRoom     = [];
            $pendingEmailByRoom  = [];
            foreach ($activeThreadIds as $tid) {
                $status = $threadStatusMap[$tid] ?? null;
                foreach (($threadToRoomsForEmails[(int) $tid] ?? []) as $rid) {
                    $receivedEmailByRoom[$rid] = ($receivedEmailByRoom[$rid] ?? 0) + 1;
                    if      ($status === \App\Models\EmailThread::STATUS_INBOX)             { $inboxEmailByRoom[$rid]   = ($inboxEmailByRoom[$rid]   ?? 0) + 1; }
                    elseif  ($status === \App\Models\EmailThread::STATUS_HOLD)              { $holdEmailByRoom[$rid]    = ($holdEmailByRoom[$rid]    ?? 0) + 1; }
                    elseif  ($status === \App\Models\EmailThread::STATUS_AWAITING_APPROVAL) { $pendingEmailByRoom[$rid] = ($pendingEmailByRoom[$rid] ?? 0) + 1; }
                }
            }

            // ★ N+1 排除 (ChatRoomController と同じパターン):
            //    旧コードはルーム数 × 4 (messageCount / unread / mention / has_my_mention) の SQL を投げており
            //    113 ルームなら ~450 クエリでチャット画面の初期表示が数秒固まる主因だった.
            //    GROUP BY で 3 クエリ + EXISTS マップで全部済むようバッチ化する.
            $roomIdsArr = $roomsRaw->pluck('id')->all();
            $messageCountByRoom = [];
            $unreadCountByRoom  = [];
            $mentionCountByRoom = [];
            $hasMentionByRoom   = [];
            try {
                // (1) message_count = 全メッセージ件数
                $messageCountByRoom = ThreadComment::query()
                    ->selectRaw('chat_room_id, COUNT(*) as cnt')
                    ->whereIn('chat_room_id', $roomIdsArr)
                    ->groupBy('chat_room_id')
                    ->pluck('cnt', 'chat_room_id')->all();
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('chat msgCount batched: ' . $e->getMessage()); }
            try {
                // (2) unread_count = 自分以外の投稿で last_read_at より後 (ルームごとに lastRead が違うので OR で組む)
                $unreadCountByRoom = ThreadComment::query()
                    ->selectRaw('chat_room_id, COUNT(*) as cnt')
                    ->whereIn('chat_room_id', $roomIdsArr)
                    ->where('user_id', '!=', $myId)
                    ->where(function ($q) use ($roomsRaw, $roomReadMap) {
                        foreach ($roomsRaw as $r) {
                            $lr = $roomReadMap[$r->id] ?? null;
                            if ($lr) {
                                $q->orWhere(function ($qq) use ($r, $lr) {
                                    $qq->where('chat_room_id', $r->id)->where('created_at', '>', $lr);
                                });
                            } else {
                                $q->orWhere('chat_room_id', $r->id);
                            }
                        }
                    })
                    ->groupBy('chat_room_id')
                    ->pluck('cnt', 'chat_room_id')->all();
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('chat unread batched: ' . $e->getMessage()); }
            if ($myName !== '') {
                try {
                    // (3) mention_count = (2) と同条件 + content に @自分名
                    $mentionCountByRoom = ThreadComment::query()
                        ->selectRaw('chat_room_id, COUNT(*) as cnt')
                        ->whereIn('chat_room_id', $roomIdsArr)
                        ->where('user_id', '!=', $myId)
                        ->where('content', 'like', '%@' . $myName . '%')
                        ->where(function ($q) use ($roomsRaw, $roomReadMap) {
                            foreach ($roomsRaw as $r) {
                                $lr = $roomReadMap[$r->id] ?? null;
                                if ($lr) {
                                    $q->orWhere(function ($qq) use ($r, $lr) {
                                        $qq->where('chat_room_id', $r->id)->where('created_at', '>', $lr);
                                    });
                                } else {
                                    $q->orWhere('chat_room_id', $r->id);
                                }
                            }
                        })
                        ->groupBy('chat_room_id')
                        ->pluck('cnt', 'chat_room_id')->all();
                } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('chat mention batched: ' . $e->getMessage()); }
                try {
                    // (4) has_my_mention = 履歴全体 (last_read 関係なし) で 1 件でも自分宛メンションがあるか
                    $hasMentionByRoom = ThreadComment::query()
                        ->selectRaw('chat_room_id, COUNT(*) as cnt')
                        ->whereIn('chat_room_id', $roomIdsArr)
                        ->where('user_id', '!=', $myId)
                        ->where('content', 'like', '%@' . $myName . '%')
                        ->groupBy('chat_room_id')
                        ->pluck('cnt', 'chat_room_id')->all();
                } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('chat hasMention batched: ' . $e->getMessage()); }
            }

            $rooms = $roomsRaw
                ->map(function ($r) use ($myName, $myId, $pinnedRoomIds, $receivedEmailByRoom,
                                        $inboxEmailByRoom, $holdEmailByRoom, $pendingEmailByRoom,
                                        $messageCountByRoom, $unreadCountByRoom, $mentionCountByRoom, $hasMentionByRoom) {
                    $latest = $r->comments->first();
                    $messageCount   = (int) ($messageCountByRoom[$r->id] ?? 0);
                    $unreadCount    = (int) ($unreadCountByRoom[$r->id]  ?? 0);
                    $myMentionCount = (int) ($mentionCountByRoom[$r->id] ?? 0);
                    return [
                        'kind'           => 'room',
                        'id'             => $r->id,
                        'name'           => $r->name,
                        'is_private'     => (bool) $r->is_private,
                        // フォルダ構成: フロントのツリー描画用 (chats/index の indent 計算で使う)
                        'parent_room_id' => $r->parent_room_id ? (int) $r->parent_room_id : null,
                        'created_by_user_id' => $r->created_by_user_id,
                        'is_mine'        => $r->created_by_user_id === $myId,
                        'message_count'  => $messageCount,
                        'mention_count'  => $myMentionCount,
                        'unread_count'   => $unreadCount,
                        // バンドル先スレッドにある未対応メール件数 (受信 + 保留 + 承認待ち).
                        // 「このルームには N 通のメールが届いている」サイドバーバッジ用 (合計).
                        'received_email_count' => (int) ($receivedEmailByRoom[$r->id] ?? 0),
                        // status 別の内訳 (バッジ色分け用: 青/琥珀/橙)
                        'inbox_email_count'   => (int) ($inboxEmailByRoom[$r->id]   ?? 0),
                        'hold_email_count'    => (int) ($holdEmailByRoom[$r->id]    ?? 0),
                        'pending_email_count' => (int) ($pendingEmailByRoom[$r->id] ?? 0),
                        // 「自分宛フィルタ」用 (履歴全体での自分宛メンションがあるか)
                        'has_my_mention' => $myName !== '' && ((int) ($hasMentionByRoom[$r->id] ?? 0)) > 0,
                        'is_pinned_chat' => isset($pinnedRoomIds[$r->id]),
                        'is_hidden'      => false, // 後段でフィルタするためここは false 固定 (列を統一)
                        // 紐付けされたスレッド ID リスト (画面側で「ルームを選択 ⇄ そのバンドル先スレッド」の青ハイライト連動に利用)
                        'bundled_thread_ids' => $r->bundledThreads->pluck('id')->all(),
                        'last_message'   => $latest ? [
                            'id'         => $latest->id,
                            'preview'    => Str::limit((string) $latest->content, 80),
                            'author'     => $latest->user?->name ?? 'システム',
                            'is_mine'    => $latest->user_id === $myId,
                            'created_at' => $latest->created_at?->format('Y/m/d H:i'),
                            'created_iso'=> $latest->created_at?->toIso8601String(),
                        ] : null,
                    ];
                })
                ->when($mentionedMe, fn($c) => $c->filter(fn($r) => $r['has_my_mention']))
                ->when(!$showHidden && !empty($hidden[UserChatHide::TYPE_ROOM]), function ($c) use ($hidden) {
                    return $c->reject(fn($r) => in_array((int) $r['id'], $hidden[UserChatHide::TYPE_ROOM], true));
                })
                ->sortByDesc('is_pinned_chat')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('listThreads: rooms query failed', ['error' => $e->getMessage()]);
            $rooms = [];
        }

        return response()->json([
            'total'   => $rows->count(),
            'threads' => $rows->all(),
            'rooms'   => $rooms,
            // フロントは「依然として非表示扱いのスレッド」のみを is_hidden 判定に使う
            // (= 非表示登録後に活動があり再表示されたスレッドは is_hidden=false とする)
            'hidden_threads' => $stillHiddenIds,
            'hidden_rooms'   => $hidden[UserChatHide::TYPE_ROOM] ?? [],
        ]);
    }

    /**
     * 全体: 全ルーム + 全スレッドのコメントを時系列で1本にマージ (読み取り専用)
     */
    public function allMessages(Request $request): JsonResponse
    {
        $limit = min(500, max(50, (int) $request->input('limit', 200)));
        $myId  = auth()->id();
        $myName = auth()->user()->name ?? '';

        $relations = ['user'];
        if (\Illuminate\Support\Facades\Schema::hasTable('chat_attachments')) {
            $relations[] = 'chatAttachments';
        }

        $comments = ThreadComment::with($relations)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        // ルーム名 / スレッド件名のマップを引く
        $roomIds   = $comments->pluck('chat_room_id')->filter()->unique()->all();
        $threadIds = $comments->pluck('thread_id')->filter()->unique()->all();
        $roomMap   = ChatRoom::whereIn('id', $roomIds)->pluck('name', 'id')->all();
        $threadMap = EmailThread::whereIn('id', $threadIds)->pluck('subject', 'id')->all();

        $out = $comments->map(function ($c) use ($myId, $roomMap, $threadMap) {
            $contextKind = $c->chat_room_id ? 'room' : 'thread';
            $contextId   = $c->chat_room_id ?: $c->thread_id;
            $contextLabel = $c->chat_room_id
                ? ('#' . ($roomMap[$c->chat_room_id] ?? '(不明なルーム)'))
                : ($threadMap[$c->thread_id] ?? '(不明なスレッド)');

            return [
                'id'           => $c->id,
                'content'      => $c->content,
                'created_at'   => $c->created_at?->format('Y/m/d H:i'),
                'user_id'      => $c->user_id,
                'author'       => $c->user?->name ?? 'システム',
                'is_author'    => $myId !== null && $c->user_id === $myId,
                'context_kind' => $contextKind,
                'context_id'   => $contextId,
                'context_label'=> $contextLabel,
                'reactions'    => [],
                'attachments'  => [],
            ];
        });

        return response()->json(['comments' => $out->values()]);
    }

    /**
     * 非表示: 自分のサイドバーからルーム/スレッドを隠す
     */
    public function hide(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:room,thread',
            'id'   => 'required|integer',
        ]);
        UserChatHide::firstOrCreate([
            'user_id' => auth()->id(),
            'hidable_type' => $data['type'],
            'hidable_id'   => $data['id'],
        ]);
        return response()->json(['status' => 'ok']);
    }

    /**
     * 表示に戻す
     */
    public function unhide(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:room,thread',
            'id'   => 'required|integer',
        ]);
        UserChatHide::where('user_id', auth()->id())
            ->where('hidable_type', $data['type'])
            ->where('hidable_id', $data['id'])
            ->delete();
        return response()->json(['status' => 'ok']);
    }
}
