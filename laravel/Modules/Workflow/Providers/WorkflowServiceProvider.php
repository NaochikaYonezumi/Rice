<?php

namespace Modules\Workflow\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class WorkflowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'workflow');
        $this->registerRoutes();
    }

    protected function registerRoutes()
    {
        Route::middleware(['web', 'auth'])->group(function() {
            Route::get('/reports', [\Modules\Workflow\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
        });
    }

    public function register()
    {
        //
    }
}
