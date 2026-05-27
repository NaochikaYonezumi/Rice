<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\EmailThread;
use App\Models\ThreadComment;
use App\Models\User;
use App\Notifications\ChatMentionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatRoomController extends Controller
{
    /**
     * ルーム管理ページ (Blade)。 メインメニュー「ルーム」から開く。
     * 振り分けルール + ルーム自体の編集 / 削除を 1 画面で扱う。
     */
    public function page()
    {
        return view('rooms.index');
    }

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $userId = auth()->id();
        // 個人 / 共有 切替: バッジカウントを scope に応じて絞り込む。
        // shared (default): owner_user_id IS NULL のスレッドのみカウント
        // personal: owner_user_id = 自分 のスレッドのみカウント
        $inboxScope = $request->input('scope', 'shared');
        if (!in_array($inboxScope, ['shared', 'personal'], true)) {
            $inboxScope = 'shared';
        }
        // 個人モード時に特定アカウントへ絞る (複数アカウント切替プルダウン用)
        // 自分が所有する有効な口座IDのみ通す (他人/未存在は無視 = 全体表示)
        $personalAccountId = null;
        if ($inboxScope === 'personal' && $request->filled('mail_account_id')) {
            $candidate = (int) $request->input('mail_account_id');
            if ($candidate > 0
                && \App\Models\MailAccount::where('id', $candidate)->where('user_id', $userId)->exists()) {
                $personalAccountId = $candidate;
            }
        }
        $applyScope = function ($q) use ($inboxScope, $userId, $personalAccountId) {
            if ($inboxScope === 'personal') {
                $q->where('owner_user_id', $userId);
                if ($personalAccountId) {
                    $q->where('mail_account_id', $personalAccountId);
                }
            } else {
                $q->whereNull('owner_user_id');
            }
            return $q;
        };
        // bundled_thread_ids も含めて返す。クライアントの「ルーム未設定」フィルタが
        // 「全ルームのバンドル ID 集合」を必要とするため。
        $roomsRaw = ChatRoom::visibleTo($userId)
            ->with(['bundledThreads:id'])
            ->orderByDesc('updated_at')
            ->get();

        // ルームごとのバッジ件数を一括集計する。
        // 「対応が必要なスレッド (= 受信 + 保留 + 承認待ち)」を数える。
        // 完了 / 対応不要 / 迷惑メール / 却下は通常タブから外れているのでバッジに入れない。
        //   - status IN (inbox, hold, pending)
        //   - ThreadMerge の source は email 一覧から非表示なので除外
        //   - emails が 0 件のスレッド (孤児) は表示されないので除外
        //   - is_manual_upload は除外
        $unhandledStatuses = [
            \App\Models\EmailThread::STATUS_INBOX,
            \App\Models\EmailThread::STATUS_HOLD,
            \App\Models\EmailThread::STATUS_AWAITING_APPROVAL,
        ];
        $threadToRooms = [];
        foreach ($roomsRaw as $r) {
            foreach ($r->bundledThreads->pluck('id')->all() as $tid) {
                $threadToRooms[(int) $tid][] = $r->id;
            }
        }
        // status 別にスレッド ID を取得して、ルーム毎の件数を「受信」「保留」「承認待ち」に分けて集計する.
        // フロントのバッジを色分け表示するため (受信=青 / 保留=琥珀 / 承認待ち=橙 等).
        $threadStatusMap = []; // tid => status
        if (!empty($threadToRooms)) {
            $rows = \App\Models\EmailThread::query()
                ->whereIn('id', array_keys($threadToRooms))
                ->whereIn('status', $unhandledStatuses)
                ->whereNotIn('id', \App\Models\ThreadMerge::select('source_thread_id_original'))
                ->has('emails')
                ->where(function ($q) {
                    $q->where('is_manual_upload', false)->orWhereNull('is_manual_upload');
                })
                ->tap($applyScope)
                ->get(['id', 'status']);
            foreach ($rows as $r) {
                $threadStatusMap[(int) $r->id] = (string) $r->status;
            }
        }
        $activeThreadIds = array_keys($threadStatusMap);

        // 互換: 旧仕様の合計 (受信 + 保留 + 承認待ち)
        $receivedEmailByRoom = [];
        // 内訳: status 別の per-room カウント
        $inboxEmailByRoom    = []; // STATUS_INBOX
        $holdEmailByRoom     = []; // STATUS_HOLD
        $pendingEmailByRoom  = []; // STATUS_AWAITING_APPROVAL
        foreach ($activeThreadIds as $tid) {
            $status = $threadStatusMap[$tid] ?? null;
            foreach (($threadToRooms[(int) $tid] ?? []) as $rid) {
                $receivedEmailByRoom[$rid] = ($receivedEmailByRoom[$rid] ?? 0) + 1;
                if      ($status === \App\Models\EmailThread::STATUS_INBOX)             { $inboxEmailByRoom[$rid]   = ($inboxEmailByRoom[$rid]   ?? 0) + 1; }
                elseif  ($status === \App\Models\EmailThread::STATUS_HOLD)              { $holdEmailByRoom[$rid]    = ($holdEmailByRoom[$rid]    ?? 0) + 1; }
                elseif  ($status === \App\Models\EmailThread::STATUS_AWAITING_APPROVAL) { $pendingEmailByRoom[$rid] = ($pendingEmailByRoom[$rid] ?? 0) + 1; }
            }
        }

        // ----- グローバル / 未振り分けカウント -----
        // どのルームにも紐付いていないスレッドが完全に見えなくなる事故を防ぐため、
        // ルーム未設定 (= ルーム未設定タブ) と、全体合計 (= すべてタブ) も同じルールで
        // 集計してフロントに渡す。
        // フィルタ条件は room 集計と同一にしておかないと数値が食い違う:
        //   status IN (inbox, hold, pending) / merge source 除外 / emails あり / is_manual_upload 除外
        $globalRows = \App\Models\EmailThread::query()
            ->whereIn('status', $unhandledStatuses)
            ->whereNotIn('id', \App\Models\ThreadMerge::select('source_thread_id_original'))
            ->has('emails')
            ->where(function ($q) {
                $q->where('is_manual_upload', false)->orWhereNull('is_manual_upload');
            })
            ->tap($applyScope)
            ->get(['id', 'status']);
        $inboxThreadIdsGlobal   = [];
        $globalInboxCount       = 0;
        $globalHoldCount        = 0;
        $globalPendingCount     = 0;
        $unroutedInboxCount     = 0;
        $unroutedHoldCount      = 0;
        $unroutedPendingCount   = 0;
        foreach ($globalRows as $row) {
            $tid = (int) $row->id;
            $inboxThreadIdsGlobal[] = $tid;
            $isUnrouted = empty($threadToRooms[$tid]);
            switch ((string) $row->status) {
                case \App\Models\EmailThread::STATUS_INBOX:
                    $globalInboxCount++;
                    if ($isUnrouted) $unroutedInboxCount++;
                    break;
                case \App\Models\EmailThread::STATUS_HOLD:
                    $globalHoldCount++;
                    if ($isUnrouted) $unroutedHoldCount++;
                    break;
                case \App\Models\EmailThread::STATUS_AWAITING_APPROVAL:
                    $globalPendingCount++;
                    if ($isUnrouted) $unroutedPendingCount++;
                    break;
            }
        }
        $globalReceivedCount   = count($inboxThreadIdsGlobal);
        $unroutedReceivedCount = $unroutedInboxCount + $unroutedHoldCount + $unroutedPendingCount;

        // ----- チャット未読 / メンション件数を一括取得 -----
        // メール画面のサイドバーでも「チャットに新着」「メンションあり」を視覚化するため。
        // ThreadChatController と同じロジックを採用 (UserRoomChatRead.last_read_at と比較).
        $myName = auth()->user()->name ?? '';
        $roomReadMap = [];
        try {
            $roomReadMap = \App\Models\UserRoomChatRead::where('user_id', $userId)
                ->pluck('last_read_at', 'chat_room_id')
                ->all();
        } catch (\Throwable) {}

        // ★ N+1 排除: 旧実装はルーム数 × 2 (unread + mention) の SQL を発行していた.
        //    50 ルームなら 100 クエリで、メニュー切替時にこのエンドポイントが数秒詰まる原因だった.
        //    GROUP BY で 2 クエリにまとめる.
        //
        //    last_read_at がルーム毎に異なるため、WHERE 句を pluck から組み立てて 1 回の SQL に落とす:
        //      SELECT chat_room_id, COUNT(*)
        //      FROM thread_comments
        //      WHERE user_id != ? AND (
        //         (chat_room_id = a AND created_at > X)
        //         OR (chat_room_id = b AND created_at > Y)
        //         OR ...
        //      )
        //      GROUP BY chat_room_id
        //    last_read_at が無いルームは created_at の制約なし.
        $unreadChatByRoom  = [];
        $mentionChatByRoom = [];
        try {
            // (1) 未読件数: ルーム ID 集合 + 各 lastRead を一括 OR 条件で 1 クエリに圧縮.
            $rows = \App\Models\ThreadComment::query()
                ->selectRaw('chat_room_id, COUNT(*) as cnt')
                ->whereIn('chat_room_id', $roomsRaw->pluck('id')->all())
                ->where('user_id', '!=', $userId)
                ->where(function ($q) use ($roomReadMap, $roomsRaw) {
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
                ->pluck('cnt', 'chat_room_id')
                ->all();
            foreach ($roomsRaw as $r) {
                $unreadChatByRoom[$r->id] = (int) ($rows[$r->id] ?? 0);
            }
        } catch (\Throwable $e) {
            // フェイルセーフ: ルーム数分の 0 を埋めて続行 (ページ全体は壊さない).
            foreach ($roomsRaw as $r) { $unreadChatByRoom[$r->id] = 0; }
            \Illuminate\Support\Facades\Log::warning('chat unread batched count failed: ' . $e->getMessage());
        }
        if ($myName !== '') {
            try {
                // (2) 未読メンション: 同様に 1 クエリ. content に @自分名 を含む条件を追加.
                $rows = \App\Models\ThreadComment::query()
                    ->selectRaw('chat_room_id, COUNT(*) as cnt')
                    ->whereIn('chat_room_id', $roomsRaw->pluck('id')->all())
                    ->where('user_id', '!=', $userId)
                    ->where('content', 'like', '%@' . $myName . '%')
                    ->where(function ($q) use ($roomReadMap, $roomsRaw) {
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
                    ->pluck('cnt', 'chat_room_id')
                    ->all();
                foreach ($roomsRaw as $r) {
                    $mentionChatByRoom[$r->id] = (int) ($rows[$r->id] ?? 0);
                }
            } catch (\Throwable $e) {
                foreach ($roomsRaw as $r) { $mentionChatByRoom[$r->id] = 0; }
                \Illuminate\Support\Facades\Log::warning('chat mention batched count failed: ' . $e->getMessage());
            }
        } else {
            foreach ($roomsRaw as $r) { $mentionChatByRoom[$r->id] = 0; }
        }

        // ===== 階層 (フォルダ構成) のための親子マップを構築 =====
        // 各ルームに対し、自身を含む全子孫の id を計算しておく.
        // 親ルームのバッジ集計に descendants の値を合算するためにも使う.
        $parentByRoom = [];
        $childrenByRoom = [];
        foreach ($roomsRaw as $r) {
            $parentByRoom[(int) $r->id] = $r->parent_room_id ? (int) $r->parent_room_id : null;
            $pid = $parentByRoom[(int) $r->id];
            if ($pid !== null) {
                $childrenByRoom[$pid] = $childrenByRoom[$pid] ?? [];
                $childrenByRoom[$pid][] = (int) $r->id;
            }
        }
        $descendantsCache = [];
        $collectDescendants = function (int $rid) use (&$descendantsCache, &$childrenByRoom, &$collectDescendants): array {
            if (isset($descendantsCache[$rid])) return $descendantsCache[$rid];
            $out = [$rid];
            foreach (($childrenByRoom[$rid] ?? []) as $cid) {
                foreach ($collectDescendants($cid) as $d) $out[] = $d;
            }
            return $descendantsCache[$rid] = $out;
        };

        // 各ルームの depth (1-based) を計算しておく. parent_room_id をたどる.
        $depthByRoom = [];
        $computeDepth = function (int $rid) use (&$depthByRoom, &$parentByRoom, &$computeDepth): int {
            if (isset($depthByRoom[$rid])) return $depthByRoom[$rid];
            $pid = $parentByRoom[$rid] ?? null;
            if ($pid === null) return $depthByRoom[$rid] = 1;
            if (!isset($parentByRoom[$pid])) return $depthByRoom[$rid] = 1;
            return $depthByRoom[$rid] = $computeDepth($pid) + 1;
        };
        foreach ($roomsRaw as $r) {
            $computeDepth((int) $r->id);
        }
        // 各ルームの subtreeMaxDepth (自身を 1 とする) も計算
        $subtreeMaxByRoom = [];
        $computeSubtreeMax = function (int $rid) use (&$subtreeMaxByRoom, &$childrenByRoom, &$computeSubtreeMax): int {
            if (isset($subtreeMaxByRoom[$rid])) return $subtreeMaxByRoom[$rid];
            $children = $childrenByRoom[$rid] ?? [];
            if (empty($children)) return $subtreeMaxByRoom[$rid] = 1;
            $m = 1;
            foreach ($children as $cid) {
                $m = max($m, $computeSubtreeMax($cid) + 1);
            }
            return $subtreeMaxByRoom[$rid] = $m;
        };
        foreach ($roomsRaw as $r) {
            $computeSubtreeMax((int) $r->id);
        }

        // ★ N+1 排除: $r->comments()->count() をルーム毎に走らせていた箇所をバッチ化.
        //    GROUP BY chat_room_id で 1 クエリにまとめて map にしておく.
        $messageCountByRoom = [];
        try {
            $messageCountByRoom = \App\Models\ThreadComment::query()
                ->selectRaw('chat_room_id, COUNT(*) as cnt')
                ->whereIn('chat_room_id', $roomsRaw->pluck('id')->all())
                ->groupBy('chat_room_id')
                ->pluck('cnt', 'chat_room_id')
                ->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('message_count batched query failed: ' . $e->getMessage());
        }

        // ===== 自分のピン留め (per-user). =====
        // UserChatPin はユーザ毎にレコードを持つので、ここで $userId のものだけを集めて
        // 後段で is_pinned_chat と並び替えに使う. 共有ルームでも個別にピン留めできる.
        $pinnedRoomIds = [];
        try {
            $pins = \App\Models\UserChatPin::where('user_id', $userId)
                ->where('pinnable_type', \App\Models\UserChatPin::TYPE_ROOM)
                ->pluck('pinnable_id')
                ->all();
            foreach ($pins as $pid) {
                $pinnedRoomIds[(int) $pid] = true;
            }
        } catch (\Throwable) {}

        $rooms = $roomsRaw->map(function ($r) use (
            $receivedEmailByRoom, $inboxEmailByRoom, $holdEmailByRoom, $pendingEmailByRoom,
            $unreadChatByRoom, $mentionChatByRoom,
            $collectDescendants, $parentByRoom, $depthByRoom, $subtreeMaxByRoom,
            $messageCountByRoom, $pinnedRoomIds
        ) {
            $tree = $collectDescendants((int) $r->id);
            // 自身 + 全子孫の集計値. 親ルームを開くと子孫スレッド / チャットも見える
            // 仕様に合わせて、バッジも親が下位の合計を持つ. 内訳 (受信/保留/承認待ち) も合算.
            $sumReceived = 0; $sumUnread = 0; $sumMention = 0;
            $sumInbox = 0; $sumHold = 0; $sumPending = 0;
            foreach ($tree as $rid) {
                $sumReceived += (int) ($receivedEmailByRoom[$rid] ?? 0);
                $sumUnread   += (int) ($unreadChatByRoom[$rid]    ?? 0);
                $sumMention  += (int) ($mentionChatByRoom[$rid]   ?? 0);
                $sumInbox    += (int) ($inboxEmailByRoom[$rid]    ?? 0);
                $sumHold     += (int) ($holdEmailByRoom[$rid]     ?? 0);
                $sumPending  += (int) ($pendingEmailByRoom[$rid]  ?? 0);
            }
            return [
                'id' => $r->id,
                'name' => $r->name,
                'is_private' => (bool) $r->is_private,
                'created_by_user_id' => $r->created_by_user_id,
                'parent_room_id' => $parentByRoom[(int) $r->id],
                // 階層関連メタ (フロントの「親に選べるか」判定に使う).
                'depth' => $depthByRoom[(int) $r->id] ?? 1,
                'subtree_max_depth' => $subtreeMaxByRoom[(int) $r->id] ?? 1,
                'created_at' => $r->created_at?->format('Y/m/d H:i'),
                'message_count' => (int) ($messageCountByRoom[$r->id] ?? 0),
                'bundled_thread_ids' => $r->bundledThreads->pluck('id')->all(),
                // 自分自身のカウント (旧仕様互換)
                'self_received_email_count' => (int) ($receivedEmailByRoom[$r->id] ?? 0),
                'self_unread_chat_count'    => (int) ($unreadChatByRoom[$r->id]    ?? 0),
                'self_mention_chat_count'   => (int) ($mentionChatByRoom[$r->id]   ?? 0),
                // 階層合算 (親ルームは子孫の合計、葉ルームは自身と同じ)
                'received_email_count' => $sumReceived,
                'unread_chat_count'    => $sumUnread,
                'mention_chat_count'   => $sumMention,
                // ★ status 別の内訳カウント (フロントでバッジ色分け表示用)
                'inbox_email_count'   => $sumInbox,
                'hold_email_count'    => $sumHold,
                'pending_email_count' => $sumPending,
                // ★ 自分がこのルームをピン留めしているか (per-user). UI 上で先頭に並べる目的.
                'is_pinned_chat' => isset($pinnedRoomIds[(int) $r->id]),
            ];
        });
        // ユーザーが非表示にしているルーム ID リスト (per-user).
        $hiddenRooms = [];
        try {
            $map = \App\Models\UserChatHide::getHiddenIdsByType((int) $userId);
            $hiddenRooms = $map[\App\Models\UserChatHide::TYPE_ROOM] ?? [];
        } catch (\Throwable) {}
        return response()->json([
            'rooms' => $rooms,
            'hidden_rooms' => $hiddenRooms,
            // 自分がピン留めしているルーム ID リスト (per-user). フロントは
            //   1. is_pinned_chat フラグでアイコンを点灯
            //   2. pinned_rooms 配列を見て並び替えの確実な再判定にも利用
            // どちらでも使えるように両方返す.
            'pinned_rooms' => array_map('intval', array_keys($pinnedRoomIds)),
            // 「すべて」「ルーム未設定」タブのバッチ用カウンタ。
            // どのルームにも紐付いていない inbox スレッドが完全に見えなくなる事故を防ぐ。
            'global_received_count'   => $globalReceivedCount,
            'unrouted_received_count' => $unroutedReceivedCount,
            // ★ status 別カウント (フロント側でバッジ色分けに使う):
            //    inbox=青 / hold=琥珀 / pending=橙
            'global_inbox_count'      => $globalInboxCount,
            'global_hold_count'       => $globalHoldCount,
            'global_pending_count'    => $globalPendingCount,
            'unrouted_inbox_count'    => $unroutedInboxCount,
            'unrouted_hold_count'     => $unroutedHoldCount,
            'unrouted_pending_count'  => $unroutedPendingCount,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_private' => 'nullable|boolean',
            'parent_room_id' => 'nullable|integer|exists:chat_rooms,id',
        ]);
        // 親ルーム指定時の整合性チェック.
        //   仕様: 共有ルームの親は共有ルームのみ / 個人ルームの親は個人ルームのみ.
        //         (= 親子で is_private が一致していなければならない)
        //   理由: 「個人ルームの下に共有ルームをぶら下げると、ツリーをたどると見えないはずの
        //         個人ルームが共有空間から透けて見える」等の事故を防ぐため.
        $parentId = $data['parent_room_id'] ?? null;
        $childIsPrivate = (bool) ($data['is_private'] ?? false);
        if ($parentId !== null) {
            $parent = ChatRoom::find((int) $parentId);
            if (!$parent || !$this->canSeeRoom($parent)) {
                return response()->json(['error' => '指定した親ルームは利用できません'], 403);
            }
            // 公開範囲の一致を要求.
            if ((bool) $parent->is_private !== $childIsPrivate) {
                return response()->json([
                    'error' => $childIsPrivate
                        ? '個人ルームの親には個人ルームのみ指定できます'
                        : '共有ルームの親には共有ルームのみ指定できます',
                ], 422);
            }
            // 最大 5 階層制限: 親の深さ + 1 が許容を越えるなら拒否
            if (($parent->depth() + 1) > ChatRoom::MAX_DEPTH) {
                return response()->json([
                    'error' => 'ルームの階層は最大 ' . ChatRoom::MAX_DEPTH . ' 階層までです (この親は最深部にあるため子を作れません)',
                ], 422);
            }
        }
        $room = ChatRoom::create([
            'name' => $data['name'],
            'is_private' => (bool) ($data['is_private'] ?? false),
            'parent_room_id' => $parentId,
            'created_by_user_id' => auth()->id(),
        ]);
        // ルーム作成直後は何も取り込まない (ユーザがルール登録するまで自動振り分けは走らない方針).
        // 旧実装ではルーム名一致で顧客スレッド/件名包含スレッドを自動取り込みしていたが、
        // 「ユーザによるフィルタがあって初めて振り分け」要望に従って廃止。
        return response()->json([
            'status'        => 'ok',
            'room'          => $room,
            'auto_bundled'  => 0,
        ], 201);
    }

    public function destroy(ChatRoom $room): JsonResponse
    {
        // 個人ルームは作成者本人だけが削除可
        if ($room->is_private && $room->created_by_user_id !== auth()->id()) {
            return response()->json(['error' => '個人ルームは作成者のみ削除できます'], 403);
        }
        $room->delete();
        return response()->json(['status' => 'ok']);
    }

    /**
     * ルームの編集 (名前 / 公開範囲)。
     *
     * 権限:
     *  - 個人ルーム (is_private = true) は作成者のみ編集可 (destroy と同じ)
     *  - 共有ルームは閲覧可ユーザー (= 全員) が編集可
     *
     * 公開範囲を「共有 → 個人」に切り替える操作は実質的にルームを作成者以外から
     * 見えなくする変更なので、非作成者が共有 → 個人に切り替えるのは禁止しておく
     * (作成者だけが許可)。逆 (個人 → 共有) は元々作成者しか触れないので問題ない。
     */
    public function update(Request $request, ChatRoom $room): JsonResponse
    {
        $userId = auth()->id();

        // 個人ルームは作成者のみ
        if ($room->is_private && $room->created_by_user_id !== $userId) {
            return response()->json(['error' => '個人ルームは作成者のみ編集できます'], 403);
        }
        // 共有ルームでも閲覧不可なら拒否 (将来 visibility が拡張された時の保険)
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは編集できません'], 403);
        }

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'is_private' => 'nullable|boolean',
        ]);

        $payload = ['name' => $data['name']];
        if (array_key_exists('is_private', $data) && $data['is_private'] !== null) {
            $nextPrivate = (bool) $data['is_private'];
            // 仕様: 共有ルームの公開範囲は全員が変更可 (= 共有 → 個人 への切替を作成者以外も実行可).
            //       個人ルームは作成者本人のみ (= 他人の個人ルームを勝手に共有化できない).
            //       メソッド冒頭で「個人ルームは作成者のみ」を既に弾いているため、
            //       ここに到達した時点で room.is_private=false なら誰でも切替可能. 追加チェック不要.
            $payload['is_private'] = $nextPrivate;
        }

        $room->update($payload);

        return response()->json([
            'status' => 'ok',
            'room'   => [
                'id'                 => $room->id,
                'name'               => $room->name,
                'is_private'         => (bool) $room->is_private,
                'created_by_user_id' => $room->created_by_user_id,
                'created_at'         => $room->created_at?->format('Y/m/d H:i'),
            ],
        ]);
    }

    public function messages(ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは閲覧できません'], 403);
        }
        $authId = auth()->id();
        $relations = ['user'];
        if (\Illuminate\Support\Facades\Schema::hasTable('chat_attachments')) {
            $relations[] = 'chatAttachments';
        }

        // 階層対応: 自身 + 全子孫ルームのメッセージとバンドル先スレッドのメッセージを統合.
        // 親ルームを開いたら下位ルームのチャットも全部見える.
        $roomIds = $room->descendantRoomIds();
        $bundledThreadIds = \Illuminate\Support\Facades\DB::table('chat_room_thread')
            ->whereIn('chat_room_id', $roomIds)
            ->pluck('email_thread_id')
            ->map(fn($i) => (int) $i)
            ->unique()
            ->values()
            ->all();
        $query = ThreadComment::with($relations)
            ->where(function ($q) use ($roomIds, $bundledThreadIds) {
                $q->whereIn('chat_room_id', $roomIds);
                if (!empty($bundledThreadIds)) {
                    $q->orWhereIn('thread_id', $bundledThreadIds);
                }
            })
            ->orderBy('created_at', 'asc');

        $comments = $query->get()->map(fn($c) => $this->presentRoomMessage($c, $authId));

        // 既読マーク (このユーザーがこのルームのチャットを開いた時点で last_read_at を更新)
        if ($authId) {
            try {
                \App\Models\UserRoomChatRead::updateOrCreate(
                    ['user_id' => $authId, 'chat_room_id' => $room->id],
                    ['last_read_at' => now()],
                );
            } catch (\Throwable $e) {
                // 未マイグレーション環境でも壊れないように握り潰す
            }
        }

        return response()->json(['comments' => $comments]);
    }

    /**
     * ルームの親 (parent_room_id) を変更する. フォルダ構成 UI の D&D で呼ばれる.
     *
     * 仕様:
     *   - 自身/子孫を親に指定するとループになるので拒否
     *   - 個人ルームは作成者のみ移動可
     *   - 共有ルームは閲覧可ユーザなら誰でも移動可 (Slack 風)
     */
    public function moveRoom(Request $request, ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは編集できません'], 403);
        }
        if ($room->is_private && $room->created_by_user_id !== auth()->id()) {
            return response()->json(['error' => '個人ルームは作成者のみ移動できます'], 403);
        }
        $data = $request->validate([
            'parent_room_id' => 'nullable|integer',
        ]);
        $newParentId = $data['parent_room_id'] !== null ? (int) $data['parent_room_id'] : null;
        if ($newParentId !== null) {
            $newParent = ChatRoom::find($newParentId);
            if (!$newParent) {
                return response()->json(['error' => '移動先の親ルームが見つかりません'], 404);
            }
            // ループ防止: 新親が自分自身 / 自分の子孫だったら NG
            $bad = $room->descendantRoomIds();
            if (in_array($newParentId, $bad, true)) {
                return response()->json(['error' => 'このルームの子孫を親に指定することはできません'], 422);
            }
            // 公開範囲の一致を要求 (共有ルームは共有ルームの下にのみ / 個人ルームは個人ルームの下にのみ移動可).
            //   要望: 「共有ルームでは共有ルームのみ親に / 個人ルームでは個人ルームのみ親に選択できるように」.
            //   D&D で誤って混ぜないようサーバ側でも防御する.
            if ((bool) $newParent->is_private !== (bool) $room->is_private) {
                return response()->json([
                    'error' => $room->is_private
                        ? '個人ルームの親には個人ルームのみ指定できます'
                        : '共有ルームの親には共有ルームのみ指定できます',
                ], 422);
            }
            // 最大階層制限: 「新親の depth + 自身の subtree 最大相対深さ」が MAX_DEPTH を越えるなら NG
            $newAbsoluteMax = $newParent->depth() + $room->subtreeMaxDepth();
            if ($newAbsoluteMax > ChatRoom::MAX_DEPTH) {
                return response()->json([
                    'error' => 'ルームの階層は最大 ' . ChatRoom::MAX_DEPTH . ' 階層までです (この移動で ' . $newAbsoluteMax . ' 階層になります)',
                ], 422);
            }
        }
        $room->parent_room_id = $newParentId;
        $room->save();
        return response()->json(['status' => 'ok']);
    }

    /**
     * source ルームの中身 (バンドルスレッド / チャット / Wiki / ルーティングルール / 子ルーム) を
     * target ルームに統合し、source 自身を削除する.
     *
     * リクエスト body: { target_room_id: int }
     *
     * 注意点:
     *   - 同一ルームは弾く
     *   - target が source の子孫だとループ的におかしくなるので弾く
     *   - 個人ルームは作成者のみ操作可
     */
    public function mergeRoom(Request $request, ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは操作できません'], 403);
        }
        if ($room->is_private && $room->created_by_user_id !== auth()->id()) {
            return response()->json(['error' => '個人ルームは作成者のみマージできます'], 403);
        }
        $data = $request->validate(['target_room_id' => 'required|integer|exists:chat_rooms,id']);
        $targetId = (int) $data['target_room_id'];
        if ($targetId === $room->id) {
            return response()->json(['error' => '同じルームにマージできません'], 422);
        }
        $target = ChatRoom::find($targetId);
        if (!$target) return response()->json(['error' => 'マージ先が見つかりません'], 404);
        if (!$this->canSeeRoom($target)) {
            return response()->json(['error' => 'マージ先のルームは閲覧できません'], 403);
        }
        // target が source の子孫だとループ
        if (in_array($targetId, $room->descendantRoomIds(), true)) {
            return response()->json(['error' => 'このルームの子孫をマージ先には指定できません'], 422);
        }
        // 公開範囲の一致を要求 (親ルール側 (#25) と同じ整合性ルール).
        //   共有ルーム ⇔ 共有ルーム / 個人ルーム ⇔ 個人ルーム のみ統合可.
        //   違反するとマージ後のツリーで「共有空間に個人ルームが透ける」状態が発生しうるため.
        if ((bool) $target->is_private !== (bool) $room->is_private) {
            return response()->json([
                'error' => $room->is_private
                    ? '個人ルームは個人ルームにのみ統合できます'
                    : '共有ルームは共有ルームにのみ統合できます',
            ], 422);
        }
        // 階層制限: source の子ルーム達が target の直下に移るので、
        //   target.depth + (source.subtreeMaxDepth - 1) <= MAX_DEPTH でなければ NG
        $sourceSubMax = $room->subtreeMaxDepth();
        if ($sourceSubMax >= 2) { // source に子がいる場合のみ階層が伸びる
            $newAbsoluteMax = $target->depth() + ($sourceSubMax - 1);
            if ($newAbsoluteMax > ChatRoom::MAX_DEPTH) {
                return response()->json([
                    'error' => 'マージするとルームの階層が最大 ' . ChatRoom::MAX_DEPTH . ' 階層を越えます (マージ後 ' . $newAbsoluteMax . ' 階層になります)',
                ], 422);
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($room, $target) {
            // 1) バンドルスレッド: target にも紐付け
            $threadIds = $room->bundledThreads()->pluck('email_threads.id')->all();
            if (!empty($threadIds)) {
                $target->bundledThreads()->syncWithoutDetaching($threadIds);
            }
            // 2) チャット (ThreadComment) を target に付け替え
            \App\Models\ThreadComment::where('chat_room_id', $room->id)
                ->update(['chat_room_id' => $target->id]);
            // 3) 子ルームを target の直下へ
            ChatRoom::where('parent_room_id', $room->id)
                ->update(['parent_room_id' => $target->id]);
            // 4) 振り分けルールも target に移管 (重複は気にせず単純に移動)
            try {
                \App\Models\ChatRoomRoutingRule::where('chat_room_id', $room->id)
                    ->update(['chat_room_id' => $target->id]);
            } catch (\Throwable) {}
            // 5) Wiki カード移管
            try {
                \App\Models\ChatRoomWiki::where('chat_room_id', $room->id)
                    ->update(['chat_room_id' => $target->id]);
            } catch (\Throwable) {}
            // 6) source 削除 (cascade で chat_room_thread の pivot 行は自動消える)
            $room->delete();
            $target->touch();
        });

        return response()->json(['status' => 'ok', 'target_room_id' => $targetId]);
    }

    /**
     * このルームに紐づいているメールスレッド一覧.
     *
     * 階層化対応:
     *   - 親ルームを開いた場合は子ルーム / 孫ルーム... の紐付けスレッドも含める.
     *   - つまり A の子に B, B の子に C のとき、A を開くと A+B+C のスレッドが返る.
     */
    public function bundledThreads(ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは閲覧できません'], 403);
        }
        $roomIds = $room->descendantRoomIds();
        $threads = \App\Models\EmailThread::query()
            ->with('latestEmail')
            ->whereHas('chatRooms', function ($q) use ($roomIds) {
                $q->whereIn('chat_rooms.id', $roomIds);
            })
            ->orderByDesc('last_email_at')
            ->get()
            ->map(fn($t) => [
                'id'      => $t->id,
                'subject' => $t->subject ?: '(無題)',
                'status'  => $t->status,
                'ticket_number' => $t->ticket_number,
                'last_email_at' => $t->last_email_at?->format('Y/m/d H:i'),
            ]);
        return response()->json(['threads' => $threads]);
    }

    /**
     * 既存のメールスレッドをこのルームに紐づける (まとめ機能)
     */
    public function attachThread(Request $request, ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは閲覧できません'], 403);
        }
        $data = $request->validate(['thread_id' => 'required|integer|exists:email_threads,id']);
        $room->bundledThreads()->syncWithoutDetaching([(int) $data['thread_id']]);
        $room->touch();
        return response()->json(['status' => 'ok']);
    }

    /**
     * 紐付けを外す
     */
    public function detachThread(ChatRoom $room, EmailThread $thread): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは閲覧できません'], 403);
        }
        $room->bundledThreads()->detach($thread->id);
        $room->touch();
        return response()->json(['status' => 'ok']);
    }

    /**
     * このルームに束ねられたメールスレッドの全メールを返す (チャット画面で覗き見用)
     */
    public function bundledEmails(ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは閲覧できません'], 403);
        }
        // 階層対応: 自身 + 全子孫ルームに紐付くスレッドのメール全部.
        $roomIds = $room->descendantRoomIds();
        $threadIds = \Illuminate\Support\Facades\DB::table('chat_room_thread')
            ->whereIn('chat_room_id', $roomIds)
            ->pluck('email_thread_id')
            ->map(fn($i) => (int) $i)
            ->unique()
            ->values()
            ->all();
        if (empty($threadIds)) {
            return response()->json(['emails' => []]);
        }
        $emails = \App\Models\Email::whereIn('thread_id', $threadIds)
            ->with('thread:id,subject', 'attachments')
            ->orderByDesc('received_at')
            ->limit(200)
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'thread_id'      => $e->thread_id,
                'thread_subject' => $e->thread?->subject,
                'subject'        => $e->subject,
                'from_label'     => $e->from_label,
                'from_address'   => $e->from_address,
                'to_address'     => $e->to_address,
                'plain_body'     => \Illuminate\Support\Str::limit($e->plain_body, 400),
                'received_at'    => $e->received_at?->format('Y/m/d H:i'),
                'attachments_count' => $e->attachments->count(),
            ]);
        return response()->json(['emails' => $emails]);
    }

    /**
     * ルームのレポート (旧称「共有ルーム専用」だったが、個人ルームでも利用可に変更)。
     * - 共有ルーム: 閲覧可ユーザー (= 全員) が読み書き可
     * - 個人ルーム: 作成者本人のみ ($this->canSeeRoom で担保)
     *   → 個人ルームのレポートは事実上「自分専用メモ」になり、他者には見えない
     */
    public function getReport(ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        return response()->json([
            'content'    => (string) ($room->report_content ?? ''),
            'updated_at' => $room->report_updated_at?->format('Y/m/d H:i'),
        ]);
    }

    public function updateReport(Request $request, ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        $data = $request->validate(['content' => 'nullable|string|max:200000']);
        $room->update([
            'report_content'    => $data['content'] ?? '',
            'report_updated_at' => now(),
        ]);
        return response()->json([
            'status'     => 'ok',
            'updated_at' => $room->report_updated_at?->format('Y/m/d H:i'),
        ]);
    }

    public function getWiki(ChatRoom $room): JsonResponse
    {
        // Wiki は共有/個人どちらも利用可 (個人ルームの場合は所有者のみ canSeeRoom で許可)
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        return response()->json([
            'content'    => (string) ($room->wiki_content ?? ''),
            'updated_at' => $room->wiki_updated_at?->format('Y/m/d H:i'),
        ]);
    }

    public function updateWiki(Request $request, ChatRoom $room): JsonResponse
    {
        // Wiki は共有/個人どちらも利用可
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        $data = $request->validate(['content' => 'nullable|string|max:200000']);
        $room->update([
            'wiki_content'    => $data['content'] ?? '',
            'wiki_updated_at' => now(),
        ]);
        return response()->json([
            'status'     => 'ok',
            'updated_at' => $room->wiki_updated_at?->format('Y/m/d H:i'),
        ]);
    }

    // ========== Wiki カード CRUD (1 ルーム N カード) ==========

    public function listWikis(ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        // 階層対応: 自身 + 全子孫ルームの Wiki カードをまとめて返す.
        // 親ルームを開いたら子ルームの Wiki も読めるようにする.
        // 各カードに `room_id` と `room_name` (出元) を付け、フロントで
        // 「このカードは子ルーム ○○ のもの」とバッジ表示できるようにする.
        $roomIds = $room->descendantRoomIds();
        $roomNameMap = ChatRoom::whereIn('id', $roomIds)->pluck('name', 'id')->all();

        $wikis = \App\Models\ChatRoomWiki::whereIn('chat_room_id', $roomIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn($w) => [
                'id'         => $w->id,
                'title'      => (string) ($w->title ?? ''),
                'content'    => (string) ($w->content ?? ''),
                'sort_order' => (int) $w->sort_order,
                'updated_at' => $w->updated_at?->format('Y/m/d H:i'),
                // 階層関連: このカードがどのルームに属するか. 親ルーム表示時に「子ルームから」を見せる.
                'room_id'    => (int) $w->chat_room_id,
                'room_name'  => (string) ($roomNameMap[$w->chat_room_id] ?? ''),
                'is_own'     => ((int) $w->chat_room_id === (int) $room->id),
            ]);
        return response()->json(['wikis' => $wikis]);
    }

    public function storeWiki(Request $request, ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'content' => 'nullable|string|max:200000',
        ]);
        // 末尾に追加 (sort_order = 現在の最大 + 1)
        $maxOrder = (int) ($room->wikis()->max('sort_order') ?? -1);
        $wiki = $room->wikis()->create([
            'title'      => $data['title'] ?? 'メモ',
            'content'    => $data['content'] ?? '',
            'sort_order' => $maxOrder + 1,
        ]);
        return response()->json([
            'status' => 'ok',
            'wiki'   => [
                'id'         => $wiki->id,
                'title'      => $wiki->title,
                'content'    => $wiki->content,
                'sort_order' => $wiki->sort_order,
                'updated_at' => $wiki->updated_at?->format('Y/m/d H:i'),
            ],
        ]);
    }

    public function updateWikiCard(Request $request, ChatRoom $room, \App\Models\ChatRoomWiki $wiki): JsonResponse
    {
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        // wiki は room に属している必要
        if ((int) $wiki->chat_room_id !== (int) $room->id) {
            return response()->json(['error' => '不正な操作'], 422);
        }
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'content' => 'nullable|string|max:200000',
        ]);
        $wiki->update([
            'title'   => $data['title']   ?? $wiki->title,
            'content' => $data['content'] ?? $wiki->content,
        ]);
        return response()->json([
            'status' => 'ok',
            'wiki'   => [
                'id'         => $wiki->id,
                'title'      => $wiki->title,
                'content'    => $wiki->content,
                'sort_order' => $wiki->sort_order,
                'updated_at' => $wiki->updated_at?->format('Y/m/d H:i'),
            ],
        ]);
    }

    public function destroyWiki(ChatRoom $room, \App\Models\ChatRoomWiki $wiki): JsonResponse
    {
        if (!$this->canSeeRoom($room)) return response()->json(['error' => '閲覧不可'], 403);
        if ((int) $wiki->chat_room_id !== (int) $room->id) {
            return response()->json(['error' => '不正な操作'], 422);
        }
        $wiki->delete();
        return response()->json(['status' => 'ok']);
    }

    /**
     * 紐付けに使うスレッド候補一覧 (検索付き)
     */
    public function pickableThreads(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $query = EmailThread::orderByDesc('last_email_at')->limit(50);
        if ($q !== '') {
            $query->where('subject', 'like', "%{$q}%");
        }
        $threads = $query->get()->map(fn($t) => [
            'id' => $t->id,
            'subject' => $t->subject ?: '(無題)',
            'status'  => $t->status,
            'last_email_at' => $t->last_email_at?->format('Y/m/d H:i'),
        ]);
        return response()->json(['threads' => $threads]);
    }

    public function postMessage(Request $request, ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは閲覧できません'], 403);
        }
        $hasFiles = $request->hasFile('files');
        $data = $request->validate([
            'content' => ($hasFiles ? 'nullable' : 'required') . '|string|max:5000',
            'files'   => 'nullable|array|max:' . \App\Http\Controllers\ChatAttachmentController::MAX_FILES,
            'files.*' => 'file|max:' . (\App\Http\Controllers\ChatAttachmentController::MAX_BYTES / 1024),
        ]);
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '' && !$hasFiles) {
            return response()->json(['status' => 'error', 'message' => '内容またはファイルが必要です'], 422);
        }

        $comment = ThreadComment::create([
            'thread_id'   => null,
            'chat_room_id' => $room->id,
            'user_id'     => auth()->id(),
            'content'     => $content,
        ]);

        if ($hasFiles) {
            try {
                \App\Http\Controllers\ChatAttachmentController::storeForComment($comment->id, $request->file('files') ?? []);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('chat attachment save failed', ['error' => $e->getMessage()]);
            }
        }

        $room->touch();
        $loadRelations = ['user'];
        if (\Illuminate\Support\Facades\Schema::hasTable('chat_attachments')) {
            $loadRelations[] = 'chatAttachments';
        }
        $comment->load($loadRelations);
        // メンション通知 (本文がある時だけ)
        if ($content !== '') {
            $this->notifyMentions($comment, $content);
        }
        return response()->json([
            'status' => 'ok',
            'comment' => $this->presentRoomMessage($comment, auth()->id()),
        ], 201);
    }

    /** 個人ルームは作成者だけが閲覧可 */
    private function canSeeRoom(ChatRoom $room): bool
    {
        if (!$room->is_private) return true;
        return $room->created_by_user_id === auth()->id();
    }

    // ========== ルームごとの振り分けルール (パターン/フィルタ) ==========

    /**
     * 振り分けルール一覧。 個人ルームは作成者のみ閲覧可。
     */
    public function listRoutingRules(ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは閲覧できません'], 403);
        }
        $rules = $room->routingRules()->orderBy('type')->orderBy('id')->get()->map(fn($r) => [
            'id'              => $r->id,
            'type'            => $r->type,
            'type_label'      => \App\Models\ChatRoomRoutingRule::TYPE_LABELS[$r->type] ?? $r->type,
            'pattern'         => $r->pattern,
            // ネスト条件ツリー (新). 単一条件ルールも内部的には 1 リーフ OR グループとして
            // conditions に backfill されているので、UI 側はこれを基準に表示すれば良い.
            'logic'           => $r->logic,
            'conditions'      => $r->conditions,
            'enabled'         => (bool) $r->enabled,
            'match_count'     => (int) $r->match_count,
            'last_matched_at' => $r->last_matched_at?->format('Y/m/d H:i'),
            'created_at'      => $r->created_at?->format('Y/m/d H:i'),
        ]);
        return response()->json([
            'rules'   => $rules,
            'types'   => \App\Models\ChatRoomRoutingRule::TYPE_LABELS,
            'logics'  => \App\Models\ChatRoomRoutingRule::LOGICS,
        ]);
    }

    /**
     * 振り分けルールを追加。
     *
     * リクエスト形式は 2 通り:
     *   (A) レガシー単一条件: { type, pattern, enabled? }
     *   (B) ネスト条件:       { conditions: { logic, items: [...] }, enabled? }
     *
     * (B) が指定された場合は AND/OR グループ評価ルールを作成. (A) の場合は従来通り.
     * conditions の中身は ChatRoomRoutingRule::validateAndNormalizeConditions で再帰検証する.
     */
    public function storeRoutingRule(Request $request, ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは編集できません'], 403);
        }

        // (B) conditions が渡されたらネスト条件ルートで処理する.
        $rawConditions = $request->input('conditions');
        if (is_array($rawConditions)) {
            try {
                $normTree = \App\Models\ChatRoomRoutingRule::validateAndNormalizeConditions($rawConditions);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            // 表示 / 旧クエリ用に代表リーフを type / pattern に backfill する.
            $firstLeaf = \App\Models\ChatRoomRoutingRule::firstLeaf($normTree) ?? ['type' => 'subject_contains', 'pattern' => ''];
            $rule = $room->routingRules()->create([
                'type'               => $firstLeaf['type'],
                'pattern'            => $firstLeaf['pattern'],
                'logic'              => $normTree['logic'] ?? 'or',
                'conditions'         => $normTree,
                'enabled'            => $request->boolean('enabled', true),
                'created_by_user_id' => auth()->id(),
            ]);
        } else {
            // (A) レガシー単一条件パス.
            $validated = $request->validate([
                'type'    => 'required|string|in:' . implode(',', \App\Models\ChatRoomRoutingRule::TYPES),
                'pattern' => 'required|string|max:500',
                'enabled' => 'nullable|boolean',
            ]);
            $pattern = \App\Models\ChatRoomRoutingRule::normalizePattern($validated['type'], $validated['pattern']);
            if ($pattern === '') {
                return response()->json(['error' => 'パターンが空です'], 422);
            }
            // 単一条件ルールは「同一 (type, pattern) の重複登録」を避ける (旧挙動).
            // ネスト条件側は意図的に重複可能 (= 同形ツリーでも別ルールとして並べたい場合がある).
            $existing = $room->routingRules()->whereNull('conditions')
                ->where('type', $validated['type'])->where('pattern', $pattern)->first();
            if ($existing) {
                $existing->update(['enabled' => $validated['enabled'] ?? true]);
                $rule = $existing;
            } else {
                // 単一条件ルールも内部的には conditions ツリー (1 リーフの OR グループ) として保存する.
                // これにより matcher / backfill は常に conditions を読めば良くなる.
                $tree = [
                    'logic' => 'or',
                    'items' => [['type' => $validated['type'], 'pattern' => $pattern]],
                ];
                $rule = $room->routingRules()->create([
                    'type'               => $validated['type'],
                    'pattern'            => $pattern,
                    'logic'              => 'or',
                    'conditions'         => $tree,
                    'enabled'            => $validated['enabled'] ?? true,
                    'created_by_user_id' => auth()->id(),
                ]);
            }
        }

        // 追加直後に既存メールへ遡及適用 (このルールにマッチする受信メールをルームに取り込む)。
        // 量が多い可能性があるので件数だけ返す。
        $backfilled = 0;
        try {
            $backfilled = \App\Services\ChatRoomAutoBundler::applyRoutingRuleBackfill($room, $rule);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('storeRoutingRule: backfill failed', [
                'room_id' => $room->id, 'rule_id' => $rule->id, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status'      => 'ok',
            'rule'        => $this->serializeRoutingRule($rule),
            'backfilled'  => $backfilled,
        ], 201);
    }

    /**
     * 振り分けルールの編集. パターン / 有効無効 / ネスト条件 ツリーをそれぞれ単独で更新可能.
     *
     *   { pattern: <string> }                 → 単一条件ルールの pattern 差し替え
     *   { enabled: <bool>   }                 → 有効/無効切り替え
     *   { conditions: { logic, items: [..] } }→ ネスト条件ツリーを差し替え
     */
    public function updateRoutingRule(Request $request, ChatRoom $room, \App\Models\ChatRoomRoutingRule $rule): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは編集できません'], 403);
        }
        if ((int) $rule->chat_room_id !== (int) $room->id) {
            return response()->json(['error' => '不正な操作'], 422);
        }
        $validated = $request->validate([
            'pattern' => 'nullable|string|max:500',
            'enabled' => 'nullable|boolean',
        ]);

        // (1) ネスト条件ツリーの差し替え (リクエストに含まれていれば).
        $rawConditions = $request->input('conditions');
        if (is_array($rawConditions)) {
            try {
                $normTree = \App\Models\ChatRoomRoutingRule::validateAndNormalizeConditions($rawConditions);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            $firstLeaf = \App\Models\ChatRoomRoutingRule::firstLeaf($normTree) ?? ['type' => $rule->type, 'pattern' => ''];
            $rule->type       = $firstLeaf['type'];
            $rule->pattern    = $firstLeaf['pattern'];
            $rule->logic      = $normTree['logic'] ?? 'or';
            $rule->conditions = $normTree;
        }

        // (2) pattern 単独差し替え (レガシー単一条件ルール用. ネスト条件ルールでは無視される).
        if (array_key_exists('pattern', $validated) && $validated['pattern'] !== null) {
            $p = \App\Models\ChatRoomRoutingRule::normalizePattern($rule->type, $validated['pattern']);
            if ($p === '') return response()->json(['error' => 'パターンが空です'], 422);
            $rule->pattern = $p;
            // 単一条件ルール (1 リーフ OR グループ) のときは conditions も同期更新する.
            if (is_array($rule->conditions) && isset($rule->conditions['items']) && count($rule->conditions['items']) === 1) {
                $items = $rule->conditions['items'];
                $items[0] = ['type' => $rule->type, 'pattern' => $p];
                $rule->conditions = ['logic' => $rule->conditions['logic'] ?? 'or', 'items' => $items];
            }
        }
        if (array_key_exists('enabled', $validated) && $validated['enabled'] !== null) {
            $rule->enabled = (bool) $validated['enabled'];
        }
        $rule->save();
        return response()->json(['status' => 'ok', 'rule' => $this->serializeRoutingRule($rule)]);
    }

    /**
     * ルールを JSON で返すときの共通シリアライザ.
     * conditions ツリーをそのまま返すので、UI 側はリーフ + グループの両方を表示できる.
     */
    protected function serializeRoutingRule(\App\Models\ChatRoomRoutingRule $rule): array
    {
        return [
            'id'         => $rule->id,
            'type'       => $rule->type,
            'type_label' => \App\Models\ChatRoomRoutingRule::TYPE_LABELS[$rule->type] ?? $rule->type,
            'pattern'    => $rule->pattern,
            'logic'      => $rule->logic,
            'conditions' => $rule->conditions,
            'enabled'    => (bool) $rule->enabled,
        ];
    }

    /**
     * 振り分けルールを削除 (紐付け済みスレッドは触らない)。
     */
    public function destroyRoutingRule(ChatRoom $room, \App\Models\ChatRoomRoutingRule $rule): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは編集できません'], 403);
        }
        if ((int) $rule->chat_room_id !== (int) $room->id) {
            return response()->json(['error' => '不正な操作'], 422);
        }
        $rule->delete();
        return response()->json(['status' => 'ok']);
    }

    /**
     * このルームの全ルールを既存メールに遡及適用する.
     * ユーザが「過去メールが取り込まれていない気がする」時に手動で再実行できる.
     */
    public function reapplyRoutingRules(ChatRoom $room): JsonResponse
    {
        if (!$this->canSeeRoom($room)) {
            return response()->json(['error' => 'このルームは編集できません'], 403);
        }
        $added = \App\Services\ChatRoomAutoBundler::reapplyAllRulesForRoom($room);
        return response()->json([
            'status'      => 'ok',
            'newly_added' => $added,
        ]);
    }

    /**
     * 「全ルームでルール再適用」 — 閲覧可能な全共有ルームの全ルールを既存メールに遡及適用する。
     * /rooms 画面の上部ボタンから呼ばれる。
     * 戻り値: ['processed_rooms' => N, 'total_newly_added' => M, 'per_room' => [...]]
     */
    public function reapplyRoutingRulesAll(): JsonResponse
    {
        $userId = auth()->id();
        $rooms = ChatRoom::visibleTo($userId)
            ->where('is_private', false)
            ->whereHas('routingRules', fn($q) => $q->where('enabled', true))
            ->get();

        $totalAdded = 0;
        $perRoom = [];
        foreach ($rooms as $r) {
            try {
                $n = \App\Services\ChatRoomAutoBundler::reapplyAllRulesForRoom($r);
                if ($n > 0) {
                    $totalAdded += $n;
                    $perRoom[] = ['room_id' => $r->id, 'room_name' => $r->name, 'newly_added' => $n];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('reapplyRoutingRulesAll: room failed', [
                    'room_id' => $r->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status'            => 'ok',
            'processed_rooms'   => $rooms->count(),
            'total_newly_added' => $totalAdded,
            'per_room'          => $perRoom,
        ]);
    }

    private function presentRoomMessage(ThreadComment $c, ?int $authId): array
    {
        $reactions = [];
        try {
            $rows = \App\Models\ChatReaction::where('comment_id', $c->id)->get();
            foreach ($rows as $r) {
                if (!isset($reactions[$r->emoji])) {
                    $reactions[$r->emoji] = ['emoji' => $r->emoji, 'count' => 0, 'me' => false];
                }
                $reactions[$r->emoji]['count']++;
                if ($r->user_id === $authId) $reactions[$r->emoji]['me'] = true;
            }
        } catch (\Throwable) {}

        $attachments = [];
        try {
            foreach (($c->relationLoaded('chatAttachments') ? $c->chatAttachments : $c->chatAttachments()->get()) as $a) {
                $attachments[] = [
                    'id'        => $a->id,
                    'filename'  => $a->filename,
                    'mime'      => $a->mime_type,
                    'size'      => (int) $a->size_bytes,
                    'is_image'  => $a->isImage(),
                    'url'       => route('chat_attachments.download', $a->id),
                    'inline_url'=> route('chat_attachments.inline',   $a->id),
                ];
            }
        } catch (\Throwable) {}

        return [
            'id'          => $c->id,
            'content'     => $c->content,
            'created_at'  => $c->created_at?->format('Y/m/d H:i'),
            'user_id'     => $c->user_id,
            'author'      => $c->user?->name ?? 'システム',
            'is_author'   => $authId !== null && $c->user_id === $authId,
            // 紐付くメールスレッド (バンドルされたスレッドのコメントの場合) / メール
            'thread_id'   => $c->thread_id,
            'email_id'    => $c->email_id ?? null,
            'reactions'   => array_values($reactions),
            'attachments' => $attachments,
        ];
    }

    private function notifyMentions(ThreadComment $comment, string $content): void
    {
        if ($content === '') return;
        $authId = auth()->id();
        $candidates = User::where('id', '!=', $authId)->get(['id', 'name']);
        $matched = [];
        foreach ($candidates as $user) {
            $name = (string) $user->name;
            if ($name === '') continue;
            $pattern = '/@' . preg_quote($name, '/') . '(?=[\s\n.,!?。、]|$)/u';
            if (preg_match($pattern, $content)) $matched[$user->id] = $user;
        }
        $mentioner = auth()->user();
        foreach ($matched as $u) {
            try { $u->notify(new ChatMentionNotification($comment, $mentioner)); } catch (\Throwable) {}
        }
    }
}
