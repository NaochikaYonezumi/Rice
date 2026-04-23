<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailThread extends Model
{
    protected $fillable = ['subject', 'last_email_at', 'tags'];

    protected $casts = [
        'last_email_at' => 'datetime',
        'tags' => 'array',
    ];

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class, 'thread_id')->orderBy('received_at');
    }

    public function latestEmail()
    {
        return $this->hasOne(Email::class, 'thread_id')->latestOfMany('received_at');
    }
}
