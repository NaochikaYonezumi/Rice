<?php

namespace App\Http\Controllers;

use App\Models\ChatReaction;
use App\Models\ThreadComment;
use App\Models\UserChatPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatExtrasController extends Controller
{
    // ===== ピン留め =====
    public function togglePin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:thread,room',
            'id'   => 'required|integer',
        ]);
        $pin = UserChatPin::where([
            'user_id'       => auth()->id(),
            'pinnable_type' => $data['type'],
            'pinnable_id'   => $data['id'],
        ])->first();
        if ($pin) {
            $pin->delete();
            return response()->json(['status' => 'ok', 'pinned' => false]);
        }
        UserChatPin::create([
            'user_id'       => auth()->id(),
            'pinnable_type' => $data['type'],
            'pinnable_id'   => $data['id'],
        ]);
        return response()->json(['status' => 'ok', 'pinned' => true]);
    }

    // ===== リアクション =====
    public function reactionsList(ThreadComment $comment): JsonResponse
    {
        $myId = auth()->id();
        $rows = ChatReaction::where('comment_id', $comment->id)->get();
        $grouped = [];
        foreach ($rows as $r) {
            if (!isset($grouped[$r->emoji])) {
                $grouped[$r->emoji] = ['emoji' => $r->emoji, 'count' => 0, 'me' => false, 'user_ids' => []];
            }
            $grouped[$r->emoji]['count']++;
            $grouped[$r->emoji]['user_ids'][] = $r->user_id;
            if ($r->user_id === $myId) $grouped[$r->emoji]['me'] = true;
        }
        return response()->json(['reactions' => array_values($grouped)]);
    }

    public function toggleReaction(Request $request, ThreadComment $comment): JsonResponse
    {
        $data = $request->validate([
            'emoji' => 'required|string|max:16',
        ]);
        $existing = ChatReaction::where([
            'comment_id' => $comment->id,
            'user_id'    => auth()->id(),
            'emoji'      => $data['emoji'],
        ])->first();
        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            ChatReaction::create([
                'comment_id' => $comment->id,
                'user_id'    => auth()->id(),
                'emoji'      => $data['emoji'],
            ]);
            $action = 'added';
        }
        // 最新の集計を返す
        $listed = $this->reactionsList($comment)->getData(true);
        return response()->json([
            'status'    => 'ok',
            'action'    => $action,
            'reactions' => $listed['reactions'] ?? [],
        ]);
    }
}
