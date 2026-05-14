<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatReaction extends Model
{
    protected $fillable = ['comment_id', 'user_id', 'emoji'];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function comment(): BelongsTo { return $this->belongsTo(ThreadComment::class, 'comment_id'); }
}
