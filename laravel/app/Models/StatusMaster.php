<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusMaster extends Model
{
    protected $fillable = ['name', 'key', 'color'];
}
