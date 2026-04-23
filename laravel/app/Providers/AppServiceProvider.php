<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        try {
            $s = \App\Models\MailSetting::getSettings();

            config([
                'mail.mailers.smtp.host'       => $s->smtp_host ?: config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port'       => $s->smtp_port ?: config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.encryption' => $s->smtp_encryption !== 'null' ? $s->smtp_encryption : null,
                'mail.mailers.smtp.username'   => $s->smtp_username ?: config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password'   => $s->smtp_password ?: config('mail.mailers.smtp.password'),
                'mail.from.address'            => $s->smtp_from_address ?: config('mail.from.address'),
                'mail.from.name'               => $s->smtp_from_name ?: config('mail.from.name'),
            ]);
        } catch (\Throwable $e) {
            // DB未準備時はスキップ
        }
    }
}
