<?php

namespace App\Console\Commands;

use App\Services\Learning\LearningEventProcessor;
use Illuminate\Console\Command;

class ProcessLearningEventsCommand extends Command
{
    protected $signature = 'bossku:process-learning-events {--limit= : Max pending events to process}';

    protected $description = 'Auto-promote eligible pending learning events to memory';

    public function handle(LearningEventProcessor $processor): int
    {
        $limit = $this->option('limit');
        $limitInt = is_numeric($limit) ? (int) $limit : null;

        $result = $processor->processPendingBatch($limitInt);

        $this->info(sprintf(
            'Learning events: %d processed, %d skipped, %d failed.',
            $result['processed'],
            $result['skipped'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
