<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Modules\MailClient\Console\Commands\FetchEmailsCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:fetch', function (\Modules\MailClient\Services\EmailFetcher $fetcher) {
    $this->info('Starting email fetch...');
    $fetcher->fetch();
    $this->info('Email fetch completed.');
})->purpose('Fetch emails from POP3/IMAP server');

