<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatQuery extends Model
{
    use HasUuids;

    protected $fillable = ['question', 'provider', 'model', 'answer', 'sources', 'status', 'error_message', 'customer_id', 'user_id'];

    protected $casts = ['sources' => 'array'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
