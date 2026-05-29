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
     *
     * ?email_id=N を付けるとそのメールに紐付くコメントだけを返す。
     * 付けないときはスレッド全体 (email_id=NULL もメール紐付きも全部) を返す。
     */
    public function index(Request $request, EmailThread $thread): JsonResponse
    {
        $authId  = auth()->id();
        // owner_user_id が他人の個人スレッドへのアクセスは拒否.
        if ($thread->owner_user_id !== null && $thread->owner_user_id !== $authId) {
            abort(403, 'このスレッドへのアクセス権がありません.');
        }
        $emailId = $request->query('email_id');

        // chat_attachments テーブル未マイグレーション環境でも壊れないよう存在チェック
        $relations = ['user'];
        if (\Illuminate\Support\Facades\Schema::hasTable('chat_attachments')) {
            $relations[] = 'chatAttachments';
        }
        $query = $thread->threadComments()->with($relations)->orderBy('created_at', 'asc');
        if ($emailId !== null && $emailId !== '') {
            $query->where('email_id', (int) $emailId);
        }
        $comments = $query->get()->map(fn($c) => $this->present($c, $authId));

        // 既読マーク (このユーザーがこのスレッドのチャットを開いた時点で last_read_at を更新)
        // メール毎チャットの閲覧でもスレッド全体の既読時刻を更新 (新着判定の単純化)
        if ($authId) {
            try {
                \App\Models\UserThreadChatRead::updateOrCreate(
                    ['user_id' => $authId, 'thread_id' => $thread->id],
                    ['last_read_at' => now()],
                );
            } catch (\Throwable $e) {
                // テーブル未マイグレーション環境でも壊れないよう握り潰す
            }
        }

        return response()->json(['comments' => $comments]);
    }

    /**
     * チャット投稿
     *
     * body.email_id を渡すとそのメールに紐付く投稿になる (per-email chat)。
     * 省略するとスレッド全体への投稿 (email_id=NULL)。
     */
    public function store(Request $request, EmailThread $thread): JsonResponse
    {
        if ($thread->owner_user_id !== null && $thread->owner_user_id !== auth()->id()) {
            abort(403, 'このスレッドへのアクセス権がありません.');
        }
        $hasFiles = $request->hasFile('files');
        $validated = $request->validate([
            'content'  => ($hasFiles ? 'nullable' : 'required') . '|string|max:5000',
            'email_id' => 'nullable|integer|exists:emails,id',
            'files'    => 'nullable|array|max:' . \App\Http\Controllers\ChatAttachmentController::MAX_FILES,
            'files.*'  => 'file|max:' . (\App\Http\Controllers\ChatAttachmentController::MAX_BYTES / 1024),  // KB
        ]);

        $content = trim((string) ($validated['content'] ?? ''));
        $emailId = $validated['email_id'] ?? null;

        // 念のため email がこのスレッドに属するか検証
        if ($emailId) {
            $belongs = \App\Models\Email::where('id', $emailId)->where('thread_id', $thread->id)->exists();
            if (!$belongs) {
                return response()->json(['status' => 'error', 'message' => 'メールが一致しません'], 422);
            }
        }

        if ($content === '' && !$hasFiles) {
            return response()->json(['status' => 'error', 'message' => '内容またはファイルが必要です'], 422);
        }

        $comment = ThreadComment::create([
            'thread_id' => $thread->id,
            'email_id'  => $emailId,
            'user_id'   => auth()->id(),
            'content'   => $content,
        ]);

        if ($hasFiles) {
            try {
                \App\Http\Controllers\ChatAttachmentController::storeForComment($comment->id, $request->file('files') ?? []);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('chat attachment save failed', ['error' => $e->getMessage()]);
            }
        }

        $loadRelations = ['user', 'thread'];
        if (\Illuminate\Support\Facades\Schema::hasTable('chat_attachments')) {
            $loadRelations[] = 'chatAttachments';
        }
        $comment->load($loadRelations);

        // @ユーザー名 メンションを検出し、該当ユーザーへ通知を送信 (本人の自己メンションは除外)
        if ($content !== '') {
            $this->notifyMentionedUsers($comment, $content);
        }

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
        // 絵文字リアクション集計
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

        // 添付ファイル一覧
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
            'created_iso' => $c->created_at?->toIso8601String(),
            'user_id'     => $c->user_id,
            'email_id'    => $c->email_id,
            'author'      => $c->user?->name ?? 'システム',
            'is_author'   => $authId !== null && $c->user_id === $authId,
            'reactions'   => array_values($reactions),
            'attachments' => $attachments,
        ];
    }
}
