<?php

namespace App\Console\Commands;

use App\Jobs\PollScmReactionsJob;
use Illuminate\Console\Command;

class PollScmReactionsCommand extends Command
{
    protected $signature = 'bossku:poll-scm-reactions';

    protected $description = 'Poll GitHub SCM state and execute configured reactions for active runs';

    public function handle(): int
    {
        PollScmReactionsJob::dispatchSync();

        $this->info('SCM reaction poll completed.');

        return self::SUCCESS;
    }
}
