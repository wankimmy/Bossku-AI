<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceGraphTest extends TestCase
{
    use RefreshDatabase;
    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bk_graph_'.uniqid();
        File::ensureDirectoryExists($this->repo.'/ai-assistant/skills/alpha-skill');
        File::put($this->repo.'/skill-index.json', json_encode([
            'version' => 'test-1',
            'skills' => [
                [
                    'id' => 'alpha-skill',
                    'triggers' => ['alpha mode'],
                    'keywords' => ['alpha'],
                ],
                [
                    'id' => 'beta-skill',
                    'triggers' => ['alpha mode'],
                    'keywords' => ['beta'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        File::put($this->repo.'/ai-assistant/skills/alpha-skill/SKILL.md', "---\ndescription: Alpha skill\n---\nSee bosskuai-beta-skill for more.\n");
        File::ensureDirectoryExists($this->repo.'/ai-assistant/skills/beta-skill');
        File::put($this->repo.'/ai-assistant/skills/beta-skill/SKILL.md', "---\ndescription: Beta skill\n---\nBeta body.\n");

        Project::query()->delete();
        config(['bossku.repo_root' => $this->repo]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->repo)) {
            File::deleteDirectory($this->repo);
        }
        parent::tearDown();
    }

    #[Test]
    public function workspace_graph_returns_nodes_and_edges(): void
    {
        $this->getJson('/api/workspace/graph')
            ->assertOk()
            ->assertJsonPath('version', 'test-1')
            ->assertJsonPath('node_count', 2)
            ->assertJsonPath('skills_source', 'active')
            ->assertJsonFragment(['id' => 'alpha-skill', 'label' => 'alpha-skill'])
            ->assertJsonStructure([
                'nodes' => [['id', 'label', 'category', 'depth', 'triggers']],
                'edges' => [['source', 'target', 'kind']],
            ]);
    }

    #[Test]
    public function workspace_graph_falls_back_to_toolkit_when_active_project_lacks_skill_index(): void
    {
        $toolkit = sys_get_temp_dir().'/bk_toolkit_'.uniqid();
        File::ensureDirectoryExists($toolkit.'/ai-assistant/skills/alpha-skill');
        File::put($toolkit.'/skill-index.json', json_encode([
            'version' => 'toolkit-1',
            'skills' => [
                ['id' => 'alpha-skill', 'triggers' => ['alpha'], 'keywords' => ['alpha']],
            ],
        ], JSON_THROW_ON_ERROR));
        File::put($toolkit.'/ai-assistant/skills/alpha-skill/SKILL.md', "---\ndescription: Alpha\n---\n");

        $appRepo = sys_get_temp_dir().'/bk_app_'.uniqid();
        File::ensureDirectoryExists($appRepo);

        config(['bossku.repo_root' => $toolkit]);

        Project::query()->create([
            'name' => 'my-app',
            'host_path' => '/workspace/my-app',
            'container_path' => $appRepo,
            'is_active' => true,
        ]);

        $this->getJson('/api/workspace/graph')
            ->assertOk()
            ->assertJsonPath('version', 'toolkit-1')
            ->assertJsonPath('skills_source', 'toolkit')
            ->assertJsonPath('node_count', 1);

        File::deleteDirectory($toolkit);
        File::deleteDirectory($appRepo);
    }
}
