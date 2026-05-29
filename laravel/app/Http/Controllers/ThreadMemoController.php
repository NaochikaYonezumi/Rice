<?php

namespace App\Http\Controllers;

use App\Models\EmailThread;
use App\Models\ThreadMemo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ThreadMemoController extends Controller
{
    /**
     * 他人の個人スレッドへのアクセスを拒否する共通ガード.
     */
    protected function authorizeAccess(EmailThread $thread): void
    {
        if ($thread->owner_user_id !== null && $thread->owner_user_id !== auth()->id()) {
            abort(403, 'このスレッドへのアクセス権がありません.');
        }
    }

    public function store(Request $request, EmailThread $thread): JsonResponse
    {
        $this->authorizeAccess($thread);
        $request->validate([
            'content' => 'required|string',
        ]);

        $memo = ThreadMemo::create([
            'thread_id' => $thread->id,
            // 'user_id' => $request->user()->id, // In a real app. We'll leave it null for this demo if no auth
            'content' => $request->content,
        ]);

        $memo->load('user');

        return response()->json(['status' => 'ok', 'memo' => [
            'id' => $memo->id,
            'content' => $memo->content,
            'created_at' => $memo->created_at?->format('Y/m/d H:i'),
            'author' => $memo->user ? $memo->user->name : 'System'
        ]]);
    }

    public function index(EmailThread $thread): JsonResponse
    {
        $this->authorizeAccess($thread);
        $memos = $thread->threadMemos()->with('user')->orderBy('created_at', 'desc')->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'content' => $m->content,
                'created_at' => $m->created_at?->format('Y/m/d H:i'),
                'author' => $m->user ? $m->user->name : 'System',
            ];
        });

        return response()->json(['memos' => $memos]);
    }
}
