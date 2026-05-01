<?php

namespace Modules\AIReply\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AIReplyServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerRoutes();
    }

    protected function registerRoutes()
    {
        Route::middleware(['web', 'auth'])->group(function() {
            Route::post('/threads/{thread}/ai-generate', [\Modules\AIReply\Http\Controllers\AIReplyController::class, 'generate'])->name('ai.generate');
        });
    }

    public function register()
    {
        //
    }
}
