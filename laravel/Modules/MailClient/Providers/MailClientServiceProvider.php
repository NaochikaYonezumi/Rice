<?php

namespace Modules\MailClient\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MailClient\Console\Commands\FetchEmailsCommand;

class MailClientServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchEmailsCommand::class,
            ]);
        }
        
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register()
    {
        //
    }
}
