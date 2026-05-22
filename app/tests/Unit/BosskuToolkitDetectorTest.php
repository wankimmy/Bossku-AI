<?php

namespace Tests\Unit;

use App\Services\Project\BosskuToolkitDetector;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BosskuToolkitDetectorTest extends TestCase
{
    #[Test]
    public function it_detects_bossku_layout_in_temp_directory(): void
    {
        $root = sys_get_temp_dir().'/bk_toolkit_'.uniqid();
        File::ensureDirectoryExists($root.'/app/app/Services/Orchestrator');
        File::ensureDirectoryExists($root.'/web');
        File::put($root.'/docker-compose.yml', 'services: {}');
        File::put(
            $root.'/app/app/Services/Orchestrator/OrchestratorService.php',
            '<?php namespace App\Services\Orchestrator; class OrchestratorService {}',
        );

        $this->assertTrue(app(BosskuToolkitDetector::class)->isBosskuToolkitRepository($root));

        File::deleteDirectory($root);
    }

    #[Test]
    public function it_does_not_flag_generic_laravel_app(): void
    {
        $root = sys_get_temp_dir().'/bk_generic_'.uniqid();
        File::ensureDirectoryExists($root.'/app/Models');
        File::put($root.'/composer.json', '{}');

        $this->assertFalse(app(BosskuToolkitDetector::class)->isBosskuToolkitRepository($root));

        File::deleteDirectory($root);
    }
}
