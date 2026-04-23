<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'original_name', 'stored_path', 'mime_type',
        'collection', 'chunks_indexed', 'is_indexed', 'extracted_text',
    ];

    protected $casts = [
        'is_indexed' => 'boolean',
        'chunks_indexed' => 'integer',
    ];

    public function getTypeIconAttribute(): string
    {
        return match (true) {
            str_contains($this->mime_type, 'pdf') => 'PDF',
            str_contains($this->mime_type, 'word') || str_contains($this->mime_type, 'docx') => 'Word',
            str_contains($this->mime_type, 'markdown') || str_ends_with($this->original_name, '.md') => 'MD',
            default => 'File',
        };
    }
}
