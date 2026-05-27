<?php

namespace Modules\Knowledge\Providers;

use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $viewsDir = __DIR__ . '/../Resources/views';
        if (is_dir($viewsDir)) {
            $this->loadViewsFrom($viewsDir, 'knowledge');
        }
        // ルートは routes/web.php 側で一元管理する (重複登録を避けるため)
    }

    public function register()
    {
        //
    }
}
