<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAttachment extends Model
{
    protected $fillable = ['comment_id', 'filename', 'stored_path', 'mime_type', 'size_bytes'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ThreadComment::class, 'comment_id');
    }

    public function isImage(): bool
    {
        return is_string($this->mime_type) && str_starts_with($this->mime_type, 'image/');
    }
}
