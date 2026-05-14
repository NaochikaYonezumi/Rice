<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserThreadChatRead extends Model
{
    protected $fillable = ['user_id', 'thread_id', 'last_read_at'];
    protected $casts = ['last_read_at' => 'datetime'];
}
