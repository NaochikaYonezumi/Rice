<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Custom autoloader for Modules/ directory since composer.json isn't updated
        spl_autoload_register(function ($class) {
            if (strpos($class, 'Modules\\') === 0) {
                $path = base_path(str_replace('\\', '/', $class) . '.php');
                if (file_exists($path)) {
                    require $path;
                }
            }
        });
    }

    public function boot(): void
    {
        // Register Module Views & Migrations (Manual since ServiceProviders aren't loading due to cache permissions)
        $this->loadViewsFrom(base_path('Modules/Knowledge/Resources/views'), 'knowledge');
        $this->loadViewsFrom(base_path('Modules/Workflow/Resources/views'), 'workflow');
        $this->loadViewsFrom(base_path('Modules/AIReply/Resources/views'), 'aireply');
        $this->loadViewsFrom(base_path('Modules/OAuthLogin/Resources/views'), 'oauthlogin');

        $this->loadMigrationsFrom(base_path('Modules/Workflow/Database/Migrations'));
        $this->loadMigrationsFrom(base_path('Modules/AIReply/Database/Migrations'));
        $this->loadMigrationsFrom(base_path('Modules/MailClient/Database/Migrations'));

        Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            \SocialiteProviders\Azure\AzureExtendSocialite::class . '@handle'
        );

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
