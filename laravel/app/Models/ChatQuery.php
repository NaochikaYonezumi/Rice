<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChatQuery extends Model
{
    use HasUuids;

    protected $fillable = ['question', 'provider', 'model', 'answer', 'sources', 'status', 'error_message'];

    protected $casts = ['sources' => 'array'];
}
