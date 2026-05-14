<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    protected $fillable = ['name', 'created_by_user_id'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ThreadComment::class, 'chat_room_id')->orderBy('created_at');
    }
}
