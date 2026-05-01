<?php

namespace Modules\Knowledge\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'knowledge');
        $this->registerRoutes();
    }

    protected function registerRoutes()
    {
        Route::middleware(['web', 'auth'])->group(function() {
            Route::get('/knowledge', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'index'])->name('knowledge.index');
            Route::post('/knowledge/crawl', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'crawl'])->name('knowledge.crawl');
        });
    }

    public function register()
    {
        //
    }
}
