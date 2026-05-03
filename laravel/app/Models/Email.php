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
        $html = $this->body_html ?? '';
        if ($html === '') return '';

        // 改行を保持しつつタグを除去
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // 連続した空行を圧縮
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
