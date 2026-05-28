<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    protected $fillable = ['name', 'created_by_user_id', 'parent_room_id', 'is_private', 'report_content', 'wiki_content', 'report_updated_at', 'wiki_updated_at'];

    protected $casts = [
        'is_private' => 'boolean',
        'report_updated_at' => 'datetime',
        'wiki_updated_at'   => 'datetime',
    ];

    /**
     * 指定ユーザーが閲覧できるルームのクエリ:
     *  - 公開ルーム (is_private = false) は全員見える
     *  - 個人ルーム (is_private = true) は作成者のみ
     */
    public function scopeVisibleTo($query, ?int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_private', false);
            if ($userId !== null) {
                $q->orWhere(function ($qq) use ($userId) {
                    $qq->where('is_private', true)->where('created_by_user_id', $userId);
                });
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ThreadComment::class, 'chat_room_id')->orderBy('created_at');
    }

    // 紐付けられたメールスレッド (まとめ機能)
    public function bundledThreads(): BelongsToMany
    {
        return $this->belongsToMany(EmailThread::class, 'chat_room_thread', 'chat_room_id', 'email_thread_id')->withTimestamps();
    }

    // Wiki カード (複数)。sort_order 昇順 → id 昇順で安定ソート
    public function wikis(): HasMany
    {
        return $this->hasMany(ChatRoomWiki::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * 振り分けルール (パターン/フィルタ)。
     * 新着メール受信時に ChatRoomAutoBundler から評価される。
     */
    public function routingRules(): HasMany
    {
        return $this->hasMany(ChatRoomRoutingRule::class)->orderBy('id');
    }

    // ===== フォルダ構成 (ルームの階層化) =====

    /**
     * 直近の親ルーム. NULL なら自身がルート.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'parent_room_id');
    }

    /**
     * 直近の子ルーム (1 階層下).
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChatRoom::class, 'parent_room_id')->orderBy('name');
    }

    /**
     * 自身を含む全子孫ルームの ID 集合を返す (BFS, ループ防止付き).
     * 親ルームを開いたとき下位ルームのスレッド / チャットも一緒に見せるために使う.
     *
     * 例: A の descendantRoomIds() = [A, B, C]  (B は A の子, C は B の子)
     */
    public function descendantRoomIds(): array
    {
        $ids = [(int) $this->id];
        $queue = [(int) $this->id];
        $seen = [(int) $this->id => true];
        while (!empty($queue)) {
            $children = static::whereIn('parent_room_id', $queue)
                ->pluck('id')
                ->map(fn($i) => (int) $i)
                ->all();
            $queue = [];
            foreach ($children as $cid) {
                if (isset($seen[$cid])) continue;
                $seen[$cid] = true;
                $ids[] = $cid;
                $queue[] = $cid;
            }
        }
        return $ids;
    }

    /**
     * 指定ルーム ID とその全子孫の ID をひとまとめに返す静的ヘルパ.
     * (インスタンス無しでも使えるよう用意)
     */
    public static function collectRoomTreeIds(int $rootId): array
    {
        $r = static::find($rootId);
        return $r ? $r->descendantRoomIds() : [];
    }

    /**
     * 最大階層数 (1 = ルート単独、2 = A > B、…、5 = A > B > C > D > E).
     * これを超えるネストはバックエンドで拒否する.
     */
    public const MAX_DEPTH = 5;

    /**
     * このルームの階層深さ (1-based). ルートは 1.
     * 親チェーンを辿って数える. ループに陥らないよう seen で防御.
     */
    public function depth(): int
    {
        $d = 1;
        $cur = $this->parent_room_id ? (int) $this->parent_room_id : null;
        $seen = [(int) $this->id => true];
        while ($cur !== null) {
            if (isset($seen[$cur])) break; // ループ防御
            $seen[$cur] = true;
            $d++;
            if ($d > 100) break; // 安全弁
            $parent = static::find($cur);
            $cur = $parent && $parent->parent_room_id ? (int) $parent->parent_room_id : null;
        }
        return $d;
    }

    /**
     * このルームの subtree (自身 + 全子孫) の中で最も深いものの相対深さ.
     * 自身を 1 とする. 例: A の子に B しかなければ 2、A > B > C なら 3.
     * 「移動先の深さ + これ <= MAX_DEPTH」 でないとループ後の階層が許容を越える.
     */
    public function subtreeMaxDepth(): int
    {
        // BFS で各ノードの subtree 相対深さを求める
        $depthMap = [(int) $this->id => 1];
        $queue = [(int) $this->id];
        $max = 1;
        while (!empty($queue)) {
            $current = array_shift($queue);
            $children = static::where('parent_room_id', $current)->pluck('id')->all();
            foreach ($children as $cid) {
                $cid = (int) $cid;
                if (isset($depthMap[$cid])) continue;
                $depthMap[$cid] = $depthMap[$current] + 1;
                if ($depthMap[$cid] > $max) $max = $depthMap[$cid];
                $queue[] = $cid;
            }
        }
        return $max;
    }
}
