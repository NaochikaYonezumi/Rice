<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'smtp_from_address', 'smtp_from_name',
        'inbox_protocol',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'pop_host', 'pop_port', 'pop_encryption', 'pop_username', 'pop_password',
    ];

    public static function getSettings(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'inbox_protocol' => 'imap',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'pop_port' => 995,
            'pop_encryption' => 'ssl',
        ]);
    }
}
