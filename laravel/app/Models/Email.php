<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    protected $fillable = [
        'thread_id', 'message_id', 'in_reply_to', 'subject',
        'from_address', 'from_name', 'to_address', 'cc', 'bcc',
        'body_text', 'body_html', 'is_read', 'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    protected $appends = ['from_label', 'plain_body'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function getFromLabelAttribute(): string
    {
        return $this->from_name ?: $this->from_address;
    }

    public function getPlainBodyAttribute(): string
    {
        if ($this->body_text) {
            return $this->body_text;
        }
        return strip_tags($this->body_html ?? '');
    }
}
