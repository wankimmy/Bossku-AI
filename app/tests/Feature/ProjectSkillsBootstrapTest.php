<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectSkillsBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private string $toolkit;

    private string $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolkit = sys_get_temp_dir().'/bk_toolkit_'.uniqid();
        $this->target = sys_get_temp_dir().'/bk_target_'.uniqid();
        File::ensureDirectoryExists($this->toolkit.'/ai-assistant/skills/demo-skill');
        File::put($this->toolkit.'/skill-index.json', json_encode([
            'version' => 1,
            'skills' => [['id' => 'demo-skill', 'name' => 'Demo']],
        ]));
        File::put($this->toolkit.'/ai-assistant/skills/demo-skill/SKILL.md', "---\nname: demo\n---\n# Demo");

        config(['bossku.repo_root' => $this->toolkit]);

        File::ensureDirectoryExists($this->target);

        Project::query()->create([
            'name' => 'Target app',
            'host_path' => $this->target,
            'container_path' => $this->target,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->toolkit, $this->target] as $dir) {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_bootstraps_skills_into_active_project(): void
    {
        File::put($this->target.'/skill-index.json', '{invalid');

        $response = $this->postJson('/api/project/skills/bootstrap')
            ->assertOk()
            ->assertJsonPath('project_name', 'Target app');

        $copied = $response->json('copied');
        $this->assertIsArray($copied);
        $this->assertContains('skill-index.json', $copied);

        $index = json_decode(File::get($this->target.'/skill-index.json'), true);
        $this->assertIsArray($index);
        $this->assertArrayHasKey('skills', $index);
        $this->assertFileExists($this->target.'/ai-assistant/skills/demo-skill/SKILL.md');
    }

    #[Test]
    public function it_returns_422_without_active_project(): void
    {
        Project::query()->update(['is_active' => false]);

        $this->postJson('/api/project/skills/bootstrap')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'No active project. Register and activate a project under Project → Paths first.']);
    }
}
