<?php

namespace Modules\OAuthLogin\Providers;

use Illuminate\Support\ServiceProvider;

class OAuthLoginServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $viewsDir = __DIR__ . '/../Resources/views';
        if (is_dir($viewsDir)) {
            $this->loadViewsFrom($viewsDir, 'oauthlogin');
        }
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
    }

    public function register()
    {
        //
    }
}
