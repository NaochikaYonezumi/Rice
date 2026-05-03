<?php

namespace App\Http\Controllers;

use App\Models\EmailThread;
use App\Models\ThreadComment;
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

        $comment = ThreadComment::create([
            'thread_id' => $thread->id,
            'user_id'   => auth()->id(),
            'content'   => trim($validated['content']),
        ]);
        $comment->load('user');

        return response()->json([
            'status'  => 'ok',
            'comment' => $this->present($comment, auth()->id()),
        ], 201);
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
