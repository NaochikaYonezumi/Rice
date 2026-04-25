<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiSetting extends Model
{
    protected $fillable = ['anthropic_api_key', 'gemini_api_key', 'default_provider', 'default_model', 'default_reply_prompt'];

    public static function getSettings(): self
    {
        return self::firstOrCreate([]);
    }

    public function setAnthropicApiKeyAttribute(?string $value): void
    {
        $this->attributes['anthropic_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAnthropicApiKeyAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setGeminiApiKeyAttribute(?string $value): void
    {
        $this->attributes['gemini_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getGeminiApiKeyAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
