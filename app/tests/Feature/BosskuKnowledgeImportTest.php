<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BosskuKnowledgeImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_a_skill_md_from_temp_repo(): void
    {
        $repo = sys_get_temp_dir().'/bktest_'.uniqid();
        File::ensureDirectoryExists($repo.'/ai-assistant/skills/hello-import');
        File::put(
            $repo.'/ai-assistant/skills/hello-import/SKILL.md',
            "---\nname: hello-import\ndescription: Test import skill\n---\n\n## Body\nHello."
        );

        config(['bossku.repo_root' => $repo]);

        $this->artisan('bosskuai:import-knowledge', ['--fresh' => true])->assertSuccessful();

        $this->assertDatabaseHas('bossku_ai_skills', [
            'name' => 'hello-import',
        ]);

        $skill = Skill::query()->where('name', 'hello-import')->first();
        $this->assertNotNull($skill);
        $this->assertStringContainsString('Test import skill', $skill->description ?? '');

        File::deleteDirectory($repo);
    }
}
