<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapedUrl extends Model
{
    protected $fillable = ['url', 'collection', 'chunks_indexed', 'status', 'error_message'];
}
