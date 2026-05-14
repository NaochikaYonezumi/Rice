<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAiSkill extends Model
{
    protected $fillable = [
        'user_id', 'skill_key', 'name', 'description', 'system_prompt', 'sort_order',
        'is_active', 'is_default_summary', 'is_default_reply',
        'show_in_summary', 'show_in_reply',
    ];

    protected $casts = [
        'is_active'          => 'bool',
        'is_default_summary' => 'bool',
        'is_default_reply'   => 'bool',
        'show_in_summary'    => 'bool',
        'show_in_reply'      => 'bool',
        'sort_order'         => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
