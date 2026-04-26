<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SsoSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'google_client_id',
        'google_client_secret',
        'google_redirect_uri',
        'require_invitation',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'require_invitation' => 'boolean',
    ];

    /**
     * Get the current settings, creating a default if none exists.
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'is_enabled' => false,
            'google_redirect_uri' => url('/auth/google/callback'),
            'require_invitation' => true,
        ]);
    }
}
