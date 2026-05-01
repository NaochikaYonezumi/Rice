<?php

namespace Modules\MailClient\Console\Commands;

use Illuminate\Console\Command;
use Modules\MailClient\Services\EmailFetcher;

class FetchEmailsCommand extends Command
{
    protected $signature = 'mail:fetch';
    protected $description = 'Fetch emails from POP3/IMAP server';

    public function handle(EmailFetcher $fetcher)
    {
        $this->info('Starting email fetch...');
        $fetcher->fetch();
        $this->info('Email fetch completed.');
    }
}
