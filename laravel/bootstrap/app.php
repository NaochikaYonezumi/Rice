<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        // 認証済みユーザに対してリカバリーコードのセットアップを強制
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureRecoveryCodesAcknowledged::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // 5 分おきに POP3/IMAP メールを取得 (重複起動を抑制)
        // 環境変数 MAIL_FETCH_DISABLED=true で停止可能
        if (env('MAIL_FETCH_DISABLED', false) !== true && env('MAIL_FETCH_DISABLED', 'false') !== 'true') {
            $schedule->command('mail:fetch')
                ->everyFiveMinutes()
                ->withoutOverlapping(10)
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/mail-fetch.log'));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
