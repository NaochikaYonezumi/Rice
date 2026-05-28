<?php

namespace Modules\Workflow\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class WorkflowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $migDir = __DIR__ . '/../Database/Migrations';
        if (is_dir($migDir)) {
            $this->loadMigrationsFrom($migDir);
        }
        $viewsDir = __DIR__ . '/../Resources/views';
        if (is_dir($viewsDir)) {
            $this->loadViewsFrom($viewsDir, 'workflow');
        }
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
