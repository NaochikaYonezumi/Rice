<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'email', 'domain', 'rag_collection', 'notes', 'group_id', 'sort_order'];

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    public function emailThreads(): HasMany
    {
        return $this->hasMany(EmailThread::class);
    }
}
