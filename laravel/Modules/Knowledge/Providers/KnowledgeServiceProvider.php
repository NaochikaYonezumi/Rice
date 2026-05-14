<?php

namespace Modules\Knowledge\Providers;

use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'knowledge');
        // ルートは routes/web.php 側で一元管理する (重複登録を避けるため)
    }

    public function register()
    {
        //
    }
}
