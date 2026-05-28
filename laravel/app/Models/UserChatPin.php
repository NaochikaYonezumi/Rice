<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChatPin extends Model
{
    protected $fillable = ['user_id', 'pinnable_type', 'pinnable_id'];

    public const TYPE_THREAD = 'thread';
    public const TYPE_ROOM   = 'room';
}
