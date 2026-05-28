<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 つの ChatRoom に複数枚 (カード形式) で保持できる Wiki エントリ。
 */
class ChatRoomWiki extends Model
{
    protected $fillable = [
        'chat_room_id',
        'title',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }
}
