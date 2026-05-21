<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Setting;
use App\Services\Project\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectRegistryTest extends TestCase
{
    use RefreshDatabase;

    private string $workspaceParent;

    private string $repoA;

    private string $repoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceParent = sys_get_temp_dir().'/bkws_'.uniqid();
        File::ensureDirectoryExists($this->workspaceParent);

        $this->repoA = $this->workspaceParent.'/project-a';
        $this->repoB = $this->workspaceParent.'/project-b';
        File::ensureDirectoryExists($this->repoA.'/src');
        File::ensureDirectoryExists($this->repoB.'/lib');
        File::put($this->repoA.'/src/a.txt', 'alpha');
        File::put($this->repoB.'/lib/b.txt', 'beta');

        config([
            'bossku.workspace_host_prefix' => $this->workspaceParent,
            'bossku.workspace_mount' => '/workspace',
            'bossku.repo_root' => $this->repoA,
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
    public function it_registers_and_maps_host_path_to_container_path(): void
    {
        $service = app(ProjectService::class);

        $result = $service->register('Project A', $this->repoA);

        $this->assertTrue($result['created']);
        $this->assertSame('/workspace/project-a', $result['project']->container_path);
        $this->assertSame($this->normalize($this->repoA), $result['project']->host_path);
    }

    #[Test]
    public function it_rejects_paths_outside_workspace(): void
    {
        $outside = sys_get_temp_dir().'/bk_outside_'.uniqid();
        File::ensureDirectoryExists($outside);

        try {
            $this->postJson('/api/project/register', [
                'name' => 'Outside',
                'host_path' => $outside,
            ])
                ->assertStatus(422)
                ->assertJsonPath('under_workspace', false);
        }
        finally {
            if (is_dir($outside)) {
                File::deleteDirectory($outside);
            }
        }
    }

    #[Test]
    public function it_activates_a_project_and_persists_setting(): void
    {
        $a = Project::query()->create([
            'name' => 'A',
            'host_path' => $this->repoA,
            'container_path' => $this->repoA,
            'is_active' => true,
        ]);
        $b = Project::query()->create([
            'name' => 'B',
            'host_path' => $this->repoB,
            'container_path' => $this->repoB,
            'is_active' => false,
        ]);

        $this->postJson("/api/project/{$b->id}/activate")
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('project.is_active', true);

        $this->assertFalse((bool) $a->fresh()->is_active);
        $this->assertTrue((bool) $b->fresh()->is_active);
        $this->assertSame($b->id, Setting::getValue(ProjectService::SETTING_ACTIVE_PROJECT_ID));
    }

    #[Test]
    public function tree_endpoint_uses_active_project_root(): void
    {
        Project::query()->create([
            'name' => 'A',
            'host_path' => $this->repoA,
            'container_path' => $this->repoA,
            'is_active' => false,
        ]);
        Project::query()->create([
            'name' => 'B',
            'host_path' => $this->repoB,
            'container_path' => $this->repoB,
            'is_active' => true,
        ]);

        $this->getJson('/api/project/tree')
            ->assertOk()
            ->assertJsonFragment(['name' => 'lib', 'type' => 'dir'])
            ->assertJsonMissing(['name' => 'src']);
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/\\'));
    }
}
