<?php

namespace App\Console\Commands;

use App\Models\BosskuAi\Project;
use App\Services\BosskuAi\CodebaseIndexService;
use Illuminate\Console\Command;

class IndexCodebaseCommand extends Command
{
    protected $signature = 'bosskuai:index-codebase
                            {project? : Project ID or name (defaults to the active project)}
                            {--root= : Override repo root path}
                            {--fresh : Wipe existing chunks before indexing}';

    protected $description = 'Index or re-index the active project\'s codebase for semantic code search.';

    public function handle(CodebaseIndexService $svc): int
    {
        $projectArg = $this->argument('project');
        $rootOverride = $this->option('root');

        /** @var Project|null $project */
        $project = null;
        if ($projectArg !== null) {
            $project = Project::query()
                ->where('id', $projectArg)
                ->orWhere('name', $projectArg)
                ->first();
            if ($project === null) {
                $this->error("Project not found: {$projectArg}");

                return self::FAILURE;
            }
        } else {
            $project = Project::query()->where('is_active', true)->first();
        }

        $root = $rootOverride ?: ($project?->container_path ?: (string) config('bossku.repo_root'));

        if (! is_dir((string) $root)) {
            $this->error("Root directory does not exist or is not mounted: {$root}");

            return self::FAILURE;
        }

        $projectId = $project?->id;
        $projectLabel = $project?->name ?? 'default';

        if ($this->option('fresh') && $projectId !== null) {
            \App\Models\BosskuAi\CodeChunk::query()->where('project_id', $projectId)->delete();
            $this->line("Cleared existing chunks for project: {$projectLabel}");
        }

        $this->line("Indexing project <info>{$projectLabel}</info> from <comment>{$root}</comment> ...");

        $stats = $svc->indexDirectory((string) $root, $projectId);

        $this->info(sprintf(
            "Done. Files indexed: %d  Chunks: %d  Skipped (unchanged): %d  Embedded: %d",
            $stats['files'],
            $stats['chunks'],
            $stats['skipped'],
            $stats['embedded'],
        ));

        return self::SUCCESS;
    }
}
