<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTemplate extends Model
{
    protected $fillable = ['user_id', 'name', 'subject', 'body', 'sort_order'];
    protected $casts = ['sort_order' => 'int'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
