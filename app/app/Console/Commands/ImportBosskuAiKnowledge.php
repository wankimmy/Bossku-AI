<?php

namespace App\Console\Commands;

use App\Services\BosskuAi\KnowledgeImportService;
use Illuminate\Console\Command;

class ImportBosskuAiKnowledge extends Command
{
    protected $signature = 'bosskuai:import-knowledge {--fresh : Truncate BosskuAI tables before import}';

    protected $description = 'Import BosskuAI markdown knowledge (skills, rules, playbooks, checklists, references)';

    public function handle(KnowledgeImportService $service): int
    {
        $repo = (string) config('bossku.repo_root');
        if ($repo === '' || ! is_dir($repo)) {
            $this->error('BOSSKU_REPO_PATH / bossku.repo_root is not a valid directory: '.$repo);

            return self::FAILURE;
        }

        $this->info('Repository root: '.$repo);

        try {
            $stats = $service->import($this->option('fresh'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $rows = [
            ['Skills imported', $stats['skills']],
            ['Rules imported', $stats['rules']],
            ['Playbooks imported', $stats['playbooks']],
            ['Checklists imported', $stats['checklists']],
            ['References imported', $stats['references']],
            ['Commands imported', $stats['commands']],
            ['Skipped paths / files', $stats['skipped']],
            ['Parse / import errors', $stats['errors']],
        ];
        $this->table(['Metric', 'Count'], $rows);

        foreach ($stats['messages'] as $msg) {
            $this->line($msg);
        }

        return self::SUCCESS;
    }
}
