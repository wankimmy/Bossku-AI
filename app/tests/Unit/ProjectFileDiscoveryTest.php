<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Services\Project\ProjectFileDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectFileDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $workspaceParent;

    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceParent = sys_get_temp_dir().'/bkdisc_'.uniqid();
        $this->repoRoot = $this->workspaceParent.'/nested-repo';
        File::ensureDirectoryExists($this->repoRoot.'/deep/nested/level');
        File::ensureDirectoryExists($this->repoRoot.'/app/Http/Controllers');
        File::ensureDirectoryExists($this->repoRoot.'/config');
        File::ensureDirectoryExists($this->repoRoot.'/routes');
        File::put($this->repoRoot.'/deep/nested/level/DeepController.php', '<?php class DeepController {}');
        File::put($this->repoRoot.'/app/Http/Controllers/DemoController.php', '<?php namespace App\Http\Controllers; class DemoController {}');
        File::put($this->repoRoot.'/config/database.php', '<?php return [];');
        File::put($this->repoRoot.'/routes/web.php', <<<'PHP'
<?php
use App\Http\Controllers\DemoController;
Route::get('/demo', [DemoController::class, 'show']);
PHP);

        config([
            'bossku.workspace_host_prefix' => $this->workspaceParent,
            'bossku.workspace_mount' => '/workspace',
            'bossku.repo_root' => $this->repoRoot,
        ]);

        Project::query()->create([
            'name' => 'Nested',
            'host_path' => $this->normalize($this->repoRoot),
            'container_path' => $this->repoRoot,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workspaceParent)) {
            File::deleteDirectory($this->workspaceParent);
        }

        parent::tearDown();
    }

    #[Test]
    public function find_by_basename_discovers_deep_nested_file(): void
    {
        $discovery = app(ProjectFileDiscovery::class);
        $paths = $discovery->findByBasename('DeepController');

        $this->assertContains('deep/nested/level/DeepController.php', $paths);
    }

    #[Test]
    public function resolve_path_hint_finds_laravel_controller(): void
    {
        $discovery = app(ProjectFileDiscovery::class);

        $this->assertSame(
            'app/Http/Controllers/DemoController.php',
            $discovery->resolvePathHint('DemoController'),
        );
        $this->assertSame(
            'config/database.php',
            $discovery->resolvePathHint('config/database.php'),
        );
    }

    #[Test]
    public function controllers_from_routes_file_returns_controller_paths(): void
    {
        $discovery = app(ProjectFileDiscovery::class);
        $paths = $discovery->controllersFromRoutesFile();

        $this->assertContains('app/Http/Controllers/DemoController.php', $paths);
    }

    #[Test]
    public function manifest_lists_nested_php_files(): void
    {
        $discovery = app(ProjectFileDiscovery::class);
        $result = $discovery->manifest('', 1, 50, 'php');

        $this->assertGreaterThanOrEqual(3, $result['total']);
        $this->assertContains('deep/nested/level/DeepController.php', $result['paths']);
    }

    #[Test]
    public function extract_symbols_from_text_handles_config_paths_in_long_prompt(): void
    {
        $discovery = app(ProjectFileDiscovery::class);

        $longPrompt = str_repeat("Review config/app.php and config/database.php for env setup.\n", 200);
        $longPrompt .= 'Also check routes/web.php and UserSettingsController.';

        $symbols = $discovery->extractSymbolsFromText($longPrompt);

        $this->assertContains('config/app.php', $symbols);
        $this->assertContains('config/database.php', $symbols);
        $this->assertContains('routes/web.php', $symbols);
        $this->assertContains('UserSettingsController', $symbols);
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/\\'));
    }
}
