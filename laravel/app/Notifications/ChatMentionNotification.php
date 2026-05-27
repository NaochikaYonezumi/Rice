<?php

namespace App\Notifications;

use App\Models\ThreadComment;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * チャット内で @ユーザー名 のメンションを受け取った際に飛ばすデータベース通知。
 *
 * 通知ペイロード:
 *   - kind          : 'chat_mention' (UI 側で種別判定に使用)
 *   - thread_id     : 該当スレッドの ID
 *   - thread_subject: スレッド件名 (ドロップダウン表示用)
 *   - comment_id    : 該当チャットコメントの ID (スクロール先指定用)
 *   - mentioner     : メンションを送ったユーザー名
 *   - mentioner_id  : メンションを送ったユーザー ID
 *   - preview       : チャット本文の先頭抜粋
 */
class ChatMentionNotification extends Notification
{
    public function __construct(
        public ThreadComment $comment,
        public User $mentioner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $thread = $this->comment->thread;

        return [
            'kind'           => 'chat_mention',
            'thread_id'      => $this->comment->thread_id,
            'thread_subject' => $thread?->subject ?? '(無題)',
            'comment_id'     => $this->comment->id,
            'mentioner'      => $this->mentioner->name,
            'mentioner_id'   => $this->mentioner->id,
            'preview'        => Str::limit((string) $this->comment->content, 120),
        ];
    }
}
