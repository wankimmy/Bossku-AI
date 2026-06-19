<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectManifestTest extends TestCase
{
    use RefreshDatabase;

    private string $workspaceParent;

    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        if (is_file(base_path('bootstrap/cache/routes-v7.php'))) {
            unlink(base_path('bootstrap/cache/routes-v7.php'));
        }

        $this->workspaceParent = sys_get_temp_dir().'/bkmani_'.uniqid();
        $this->repoRoot = $this->workspaceParent.'/manifest-repo';
        File::ensureDirectoryExists($this->repoRoot.'/app/Http/Controllers');
        File::ensureDirectoryExists($this->repoRoot.'/routes');

        for ($i = 0; $i < 5; $i++) {
            File::put($this->repoRoot.'/app/Http/Controllers/Controller'.$i.'.php', '<?php');
        }
        File::put($this->repoRoot.'/routes/web.php', '<?php');

        config([
            'bossku.workspace_host_prefix' => $this->workspaceParent,
            'bossku.workspace_mount' => '/workspace',
            'bossku.repo_root' => $this->repoRoot,
        ]);

        Project::query()->create([
            'name' => 'Manifest',
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
    public function manifest_endpoint_returns_controller_paths(): void
    {
        $res = $this->getJson('/api/project/manifest?ext=php')
            ->assertOk()
            ->json();

        $this->assertContains('app/Http/Controllers/Controller0.php', $res['paths']);
    }

    #[Test]
    public function manifest_supports_pagination(): void
    {
        $page1 = $this->getJson('/api/project/manifest?ext=php&per_page=2&page=1')
            ->assertOk()
            ->json();

        $this->assertCount(2, $page1['paths']);
        $this->assertGreaterThanOrEqual(6, $page1['total']);
    }

    #[Test]
    public function manifest_is_rooted_in_active_project(): void
    {
        $otherRepo = $this->workspaceParent.'/other-repo';
        File::ensureDirectoryExists($otherRepo.'/only-here');
        File::put($otherRepo.'/only-here/unique.php', '<?php');

        Project::query()->create([
            'name' => 'Other',
            'host_path' => $this->normalize($otherRepo),
            'container_path' => $otherRepo,
            'is_active' => false,
        ]);

        $activePaths = $this->getJson('/api/project/manifest?ext=php')
            ->assertOk()
            ->json('paths');

        $this->assertNotContains('only-here/unique.php', $activePaths);
        $this->assertContains('app/Http/Controllers/Controller0.php', $activePaths);
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/\\'));
    }
}
