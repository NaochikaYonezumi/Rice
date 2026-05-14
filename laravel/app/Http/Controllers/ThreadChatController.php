<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\EmailThread;
use App\Models\ThreadComment;
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
        $myName      = auth()->user()->name ?? '';
        $myId        = auth()->id();

        $query = EmailThread::query()
            ->has('threadComments')
            ->with([
                'threadComments' => fn($cq) => $cq->orderByDesc('created_at')->with('user'),
                'latestEmail',
                'assignee',
                'customer',
            ]);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('subject', 'like', "%{$q}%")
                  ->orWhereHas('threadComments', fn($cq) => $cq->where('content', 'like', "%{$q}%"));
            });
        }

        if ($myCommented) {
            $query->whereHas('threadComments', fn($cq) => $cq->where('user_id', $myId));
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
                'is_pinned'       => (bool) $t->is_pinned,
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
                'thread_last_email_at' => $t->last_email_at?->format('Y/m/d H:i'),
            ];
        })
        ->when($mentionedMe, fn($c) => $c->filter(fn($r) => $r['has_my_mention']))
        // ピン留めを上に、それ以外は最新コメント時刻降順
        ->sortBy([
            ['is_pinned_chat', 'desc'],
            fn($a, $b) => strcmp($b['last_comment']['created_iso'] ?? '', $a['last_comment']['created_iso'] ?? ''),
        ])
        ->values();

        // 同時にスタンドアロンチャットルーム一覧も返す (統合チャット一覧用)
        try {
            $rooms = ChatRoom::with(['comments' => fn($q) => $q->orderByDesc('created_at')->limit(1)->with('user')])
                ->orderByDesc('updated_at')
                ->get()
                ->map(function ($r) use ($myName, $myId, $pinnedRoomIds) {
                    $latest = $r->comments->first();
                    $messageCount = ThreadComment::where('chat_room_id', $r->id)->count();
                    $myMentionCount = 0;
                    if ($myName !== '') {
                        // LIKE で簡易検出 (RegExp はドライバ依存があるため避ける)
                        $myMentionCount = ThreadComment::where('chat_room_id', $r->id)
                            ->where('content', 'like', '%@' . $myName . '%')
                            ->where('user_id', '!=', $myId)
                            ->count();
                    }
                    return [
                        'kind'           => 'room',
                        'id'             => $r->id,
                        'name'           => $r->name,
                        'message_count'  => $messageCount,
                        'mention_count'  => $myMentionCount,
                        // ルームには既読時刻ベースの未読概念がないので mention_count > 0 を mention 判定に流用
                        'has_my_mention' => $myMentionCount > 0,
                        'is_pinned_chat' => isset($pinnedRoomIds[$r->id]),
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
        ]);
    }
}
