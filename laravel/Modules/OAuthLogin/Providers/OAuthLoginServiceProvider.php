<?php

namespace Modules\OAuthLogin\Providers;

use Illuminate\Support\ServiceProvider;

class OAuthLoginServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'oauthlogin');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
    }

    public function register()
    {
        //
    }
}
