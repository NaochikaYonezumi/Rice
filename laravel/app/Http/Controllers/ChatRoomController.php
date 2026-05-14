<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\ThreadComment;
use App\Models\User;
use App\Notifications\ChatMentionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatRoomController extends Controller
{
    public function index(): JsonResponse
    {
        $rooms = ChatRoom::orderByDesc('updated_at')->get()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'created_by_user_id' => $r->created_by_user_id,
            'created_at' => $r->created_at?->format('Y/m/d H:i'),
            'message_count' => $r->comments()->count(),
        ]);
        return response()->json(['rooms' => $rooms]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $room = ChatRoom::create([
            'name' => $data['name'],
            'created_by_user_id' => auth()->id(),
        ]);
        return response()->json(['status' => 'ok', 'room' => $room], 201);
    }

    public function destroy(ChatRoom $room): JsonResponse
    {
        $room->delete();
        return response()->json(['status' => 'ok']);
    }

    public function messages(ChatRoom $room): JsonResponse
    {
        $authId = auth()->id();
        $relations = ['user'];
        if (\Illuminate\Support\Facades\Schema::hasTable('chat_attachments')) {
            $relations[] = 'chatAttachments';
        }
        $comments = ThreadComment::where('chat_room_id', $room->id)
            ->with($relations)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($c) => $this->presentRoomMessage($c, $authId));
        return response()->json(['comments' => $comments]);
    }

    public function postMessage(Request $request, ChatRoom $room): JsonResponse
    {
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
