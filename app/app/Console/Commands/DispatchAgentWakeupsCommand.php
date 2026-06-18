<?php

namespace App\Console\Commands;

use App\Services\Company\AgentWakeupDispatcher;
use Illuminate\Console\Command;

class DispatchAgentWakeupsCommand extends Command
{
    protected $signature = 'bossku:dispatch-agent-wakeups {--limit=10 : Max queued wakeups to process}';

    protected $description = 'Process queued specialist agent wakeup requests';

    public function handle(AgentWakeupDispatcher $dispatcher): int
    {
        $limit = max(1, min((int) $this->option('limit'), 50));
        $result = $dispatcher->dispatchQueued($limit);

        $this->info(sprintf(
            'Wakeups processed=%d skipped=%d failed=%d',
            $result['processed'],
            $result['skipped'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
