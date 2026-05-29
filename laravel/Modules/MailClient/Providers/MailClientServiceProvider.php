<?php

namespace Modules\MailClient\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MailClient\Console\Commands\FetchEmailsCommand;
use Modules\MailClient\Console\Commands\RedecodeBrokenSubjectsCommand;

class MailClientServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchEmailsCommand::class,
                RedecodeBrokenSubjectsCommand::class,
            ]);
        }
        
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register()
    {
        //
    }
}
