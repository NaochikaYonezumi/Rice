<?php

namespace App\Http\Controllers;

use App\Models\EmailThread;
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

        // メンション検出 (PHP 側で名前マッチ)
        $mentionPattern = $myName !== ''
            ? '/@' . preg_quote($myName, '/') . '(?=[\s\n.,!?。、]|$)/u'
            : null;

        $rows = $threads->map(function ($t) use ($mentionPattern, $myId) {
            $comments = $t->threadComments;
            $latest   = $comments->first();
            $myMentionCount = 0;
            if ($mentionPattern) {
                foreach ($comments as $c) {
                    if (preg_match($mentionPattern, (string) $c->content)) {
                        $myMentionCount++;
                    }
                }
            }

            return [
                'id'              => $t->id,
                'subject'         => $t->subject ?: '(無題)',
                'ticket_number'   => $t->ticket_number,
                'status'          => $t->status,
                'is_pinned'       => (bool) $t->is_pinned,
                'customer_name'   => $t->customer?->name,
                'assignee'        => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
                'comment_count'   => $comments->count(),
                'mention_count'   => $myMentionCount,
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
        ->when($mentionedMe, fn($c) => $c->filter(fn($r) => $r['mention_count'] > 0))
        ->sortByDesc(fn($r) => $r['last_comment']['created_iso'] ?? '')
        ->values();

        return response()->json([
            'total'   => $rows->count(),
            'threads' => $rows->all(),
        ]);
    }
}
