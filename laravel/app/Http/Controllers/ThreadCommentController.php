<?php

namespace App\Http\Controllers;

use App\Models\EmailThread;
use App\Models\ThreadComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ThreadCommentController extends Controller
{
    public function store(Request $request, EmailThread $thread): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $comment = ThreadComment::create([
            'thread_id' => $thread->id,
            // 'user_id' => $request->user()->id, // Assuming no auth for demo, or add if present
            'content' => $request->content,
        ]);

        $comment->load('user');

        return response()->json(['status' => 'ok', 'comment' => [
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at?->format('Y/m/d H:i'),
            'author' => $comment->user ? $comment->user->name : 'System',
            'is_author' => true // hardcoded true for demo, usually checking user_id === current user
        ]]);
    }

    public function index(EmailThread $thread): JsonResponse
    {
        $comments = $thread->threadComments()->with('user')->orderBy('created_at', 'asc')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'content' => $c->content,
                'created_at' => $c->created_at?->format('Y/m/d H:i'),
                'author' => $c->user ? $c->user->name : 'System',
                'is_author' => true // or check auth()->id() == $c->user_id
            ];
        });

        return response()->json(['comments' => $comments]);
    }

    public function destroy(ThreadComment $comment): JsonResponse
    {
        // Authorization check normally here
        $comment->delete();
        return response()->json(['status' => 'ok']);
    }
}
