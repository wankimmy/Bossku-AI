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

    #[Test]
    public function it_lists_workspace_folders_at_mount_root(): void
    {
        config(['bossku.workspace_mount' => $this->workspaceParent]);

        $this->getJson('/api/project/workspace-folders')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('path', '')
            ->assertJsonFragment(['name' => 'project-a', 'relative' => 'project-a'])
            ->assertJsonFragment(['name' => 'project-b', 'relative' => 'project-b']);
    }

    #[Test]
    public function it_lists_workspace_subfolders(): void
    {
        config(['bossku.workspace_mount' => $this->workspaceParent]);

        $this->getJson('/api/project/workspace-folders?path=project-a')
            ->assertOk()
            ->assertJsonPath('path', 'project-a')
            ->assertJsonFragment(['name' => 'src', 'relative' => 'project-a/src']);
    }

    #[Test]
    public function it_denies_workspace_path_traversal(): void
    {
        config(['bossku.workspace_mount' => $this->workspaceParent]);

        $this->getJson('/api/project/workspace-folders?path=../outside')
            ->assertStatus(422)
            ->assertJsonPath('available', false);
    }

    #[Test]
    public function it_registers_and_activates_container_path(): void
    {
        config(['bossku.workspace_mount' => $this->workspaceParent]);

        $containerPath = $this->workspaceParent.'/project-b';

        $this->postJson('/api/project/register-container-path', [
            'name' => 'Project B',
            'container_path' => $containerPath,
            'activate' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('project.name', 'Project B')
            ->assertJsonPath('project.is_active', true)
            ->assertJsonPath('available', true);

        $stored = Project::query()->where('name', 'Project B')->first();
        $this->assertNotNull($stored);
        $this->assertSame(realpath($containerPath), $stored->container_path);
        $this->assertTrue((bool) $stored->is_active);
    }

    #[Test]
    public function it_rejects_windows_host_path_when_workspace_prefix_is_blank(): void
    {
        config(['bossku.workspace_host_prefix' => '']);

        $this->postJson('/api/project/register', [
            'name' => 'Windows path',
            'host_path' => 'C:/Users/Admin/Documents/my-app',
        ])
            ->assertStatus(422)
            ->assertJsonPath('under_workspace', false);
    }

    #[Test]
    public function it_rejects_container_path_outside_workspace_mount(): void
    {
        config(['bossku.workspace_mount' => $this->workspaceParent]);

        $this->postJson('/api/project/register-container-path', [
            'name' => 'Outside',
            'container_path' => '/etc/passwd',
        ])
            ->assertStatus(422);
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/\\'));
    }
}
