<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailThread extends Model
{
    protected $fillable = ['subject', 'last_email_at', 'tags', 'customer_id', 'status'];

    protected $casts = [
        'last_email_at' => 'datetime',
        'tags' => 'array',
        'status' => 'string',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class, 'thread_id')->orderBy('received_at');
    }

    public function latestEmail()
    {
        return $this->hasOne(Email::class, 'thread_id')->latestOfMany('received_at');
    }

    public function threadMerges(): HasMany
    {
        return $this->hasMany(ThreadMerge::class, 'target_thread_id')->orderByDesc('created_at');
    }
}
