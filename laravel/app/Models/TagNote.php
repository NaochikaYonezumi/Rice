<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagNote extends Model
{
    protected $fillable = ['tag', 'content'];

    protected $casts = [
        'content' => 'array',
    ];
}
