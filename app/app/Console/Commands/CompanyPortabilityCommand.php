<?php

namespace App\Console\Commands;

use App\Models\BosskuAi\Project;
use App\Services\Company\CompanyPortabilityService;
use Illuminate\Console\Command;

class CompanyPortabilityCommand extends Command
{
    protected $signature = 'bosskuai:company
                            {action : export | import}
                            {--project= : Project ID or name (export; defaults to active)}
                            {--file= : Bundle file path (export target / import source)}
                            {--name= : New company name (import)}';

    protected $description = 'Export or import an entire company/org bundle (project + goals + agents) with secret scrubbing.';

    public function handle(CompanyPortabilityService $portability): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'export') {
            $project = $this->resolveProject();
            if ($project === null) {
                $this->error('No project. Pass --project=<id|name>.');

                return self::FAILURE;
            }
            $json = json_encode($portability->export($project), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
            $file = (string) $this->option('file');
            if ($file !== '') {
                file_put_contents($file, $json);
                $this->info('Exported "'.$project->name.'" → '.$file);
            } else {
                $this->line($json);
            }

            return self::SUCCESS;
        }

        if ($action === 'import') {
            $file = (string) $this->option('file');
            if ($file === '' || ! is_file($file)) {
                $this->error('Provide --file=<bundle.json>.');

                return self::FAILURE;
            }
            $bundle = json_decode((string) file_get_contents($file), true);
            if (! is_array($bundle)) {
                $this->error('Invalid bundle JSON.');

                return self::FAILURE;
            }
            $project = $portability->import($bundle, $this->option('name'));
            $this->info('Imported company "'.$project->name.'" ('.$project->id.').');

            return self::SUCCESS;
        }

        $this->error('Unknown action: '.$action.' (use export|import).');

        return self::FAILURE;
    }

    private function resolveProject(): ?Project
    {
        $arg = $this->option('project');
        if ($arg !== null && $arg !== '') {
            return Project::query()->where('id', $arg)->orWhere('name', $arg)->first();
        }

        return Project::query()->where('is_active', true)->first();
    }
}
