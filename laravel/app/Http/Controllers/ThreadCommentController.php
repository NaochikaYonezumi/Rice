<?php

namespace App\Http\Controllers;

use App\Models\EmailThread;
use App\Models\ThreadComment;
use App\Models\User;
use App\Notifications\ChatMentionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadCommentController extends Controller
{
    /**
     * スレッドのチャット一覧
     */
    public function index(EmailThread $thread): JsonResponse
    {
        $authId = auth()->id();
        $comments = $thread->threadComments()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($c) => $this->present($c, $authId));

        return response()->json(['comments' => $comments]);
    }

    /**
     * チャット投稿
     */
    public function store(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|min:1|max:5000',
        ]);

        $content = trim($validated['content']);

        $comment = ThreadComment::create([
            'thread_id' => $thread->id,
            'user_id'   => auth()->id(),
            'content'   => $content,
        ]);
        $comment->load(['user', 'thread']);

        // @ユーザー名 メンションを検出し、該当ユーザーへ通知を送信 (本人の自己メンションは除外)
        $this->notifyMentionedUsers($comment, $content);

        return response()->json([
            'status'  => 'ok',
            'comment' => $this->present($comment, auth()->id()),
        ], 201);
    }

    /**
     * チャット本文中の @ユーザー名 を抽出し、該当する User へ ChatMentionNotification を送る。
     *
     * - 一度の投稿で同じユーザーに複数回 @ があっても通知は 1 回
     * - 投稿者自身は通知対象外
     * - メンション名は完全一致 (ユーザー一覧と突き合わせ)
     */
    private function notifyMentionedUsers(ThreadComment $comment, string $content): void
    {
        if ($content === '') return;

        // 候補となるユーザー一覧を取得 (自分以外)
        $authId = auth()->id();
        $candidates = User::where('id', '!=', $authId)->get(['id', 'name']);
        if ($candidates->isEmpty()) return;

        $matched = [];
        foreach ($candidates as $user) {
            $name = (string) $user->name;
            if ($name === '') continue;
            $pattern = '/@' . preg_quote($name, '/') . '(?=[\s\n.,!?。、]|$)/u';
            if (preg_match($pattern, $content)) {
                $matched[$user->id] = $user;
            }
        }
        if (empty($matched)) return;

        $mentioner = auth()->user();
        if (!$mentioner) return;

        foreach ($matched as $user) {
            try {
                $user->notify(new ChatMentionNotification($comment, $mentioner));
            } catch (\Throwable $e) {
                // 通知失敗はチャット投稿全体を失敗させない (ログだけ残す)
                \Log::warning('ChatMentionNotification failed', [
                    'user_id' => $user->id,
                    'comment_id' => $comment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * チャット削除 (本人のみ)
     */
    public function destroy(ThreadComment $comment): JsonResponse
    {
        if ($comment->user_id !== auth()->id()) {
            return response()->json([
                'status'  => 'error',
                'message' => '自分以外のメッセージは削除できません',
            ], 403);
        }
        $comment->delete();
        return response()->json(['status' => 'ok']);
    }

    /**
     * 共通: コメント整形
     */
    private function present(ThreadComment $c, ?int $authId): array
    {
        return [
            'id'         => $c->id,
            'content'    => $c->content,
            'created_at' => $c->created_at?->format('Y/m/d H:i'),
            'created_iso'=> $c->created_at?->toIso8601String(),
            'user_id'    => $c->user_id,
            'author'     => $c->user?->name ?? 'システム',
            'is_author'  => $authId !== null && $c->user_id === $authId,
        ];
    }
}
