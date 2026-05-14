<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapedUrl extends Model
{
    public const SOURCE_URL   = 'url';
    public const SOURCE_FILE  = 'file';
    public const SOURCE_EMAIL = 'email';

    protected $fillable = [
        'url', 'source_type', 'title', 'meta', 'raw_text',
        'collection', 'chunks_indexed', 'status', 'error_message',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function displayLabel(): string
    {
        return $this->title ?: $this->url;
    }
}
