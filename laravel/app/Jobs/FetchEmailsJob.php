<?php

namespace App\Jobs;

use Modules\MailClient\Services\EmailFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class FetchEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public function handle(EmailFetcher $service): void
    {
        $count = $service->fetch();
        Log::info("FetchEmailsJob: imported {$count} emails.");
    }
}
