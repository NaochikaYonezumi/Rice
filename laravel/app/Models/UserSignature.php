<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSignature extends Model
{
    protected $fillable = ['user_id', 'name', 'body', 'is_default', 'sort_order'];
    protected $casts = ['is_default' => 'bool', 'sort_order' => 'int'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
