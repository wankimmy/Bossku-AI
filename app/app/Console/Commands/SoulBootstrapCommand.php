<?php

namespace App\Console\Commands;

use App\Models\BosskuAi\SoulVersion;
use Illuminate\Console\Command;

class SoulBootstrapCommand extends Command
{
    protected $signature = 'bosskuai:soul-bootstrap {--force : Overwrite existing active version}';

    protected $description = 'Bootstrap the BosskuAI soul from bossku/soul.md into the database';

    public function handle(): int
    {
        $path = base_path('bossku/soul.md');

        if (! file_exists($path)) {
            $this->error("Soul file not found at: {$path}");

            return self::FAILURE;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            $this->error("Failed to read soul file at: {$path}");

            return self::FAILURE;
        }

        if (SoulVersion::where('active', true)->exists()) {
            if (! $this->option('force')) {
                $this->line('Active soul version already exists. Use --force to overwrite.');

                return self::SUCCESS;
            }
        }

        SoulVersion::where('active', true)->update(['active' => false]);

        SoulVersion::create([
            'version'         => 'v1.0.0',
            'content'         => $content,
            'active'          => true,
            'change_summary'  => 'Initial soul bootstrap from soul.md',
        ]);

        $this->info('Soul v1.0.0 bootstrapped successfully.');

        return self::SUCCESS;
    }
}
