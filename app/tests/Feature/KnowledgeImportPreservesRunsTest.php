<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Artifact;
use App\Models\BosskuAi\Checklist;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Playbook;
use App\Models\BosskuAi\Rule;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\ToolCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeImportPreservesRunsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fresh_import_preserves_run_history_and_memory_but_clears_knowledge(): void
    {
        $repo = sys_get_temp_dir().'/bktest_'.uniqid();
        File::ensureDirectoryExists($repo.'/ai-assistant/skills/hello-import');
        File::put(
            $repo.'/ai-assistant/skills/hello-import/SKILL.md',
            "---\nname: hello-import\ndescription: Fresh import skill\n---\n\n## Body\nHello."
        );
        config(['bossku.repo_root' => $repo]);

        // --- Run history + memory: must survive --fresh ---
        $run = Run::factory()->create(['status' => 'completed']);
        $step = RunStep::query()->create([
            'run_id' => $run->id,
            'type' => 'executor',
            'status' => 'done',
        ]);
        ToolCall::query()->create([
            'run_id' => $run->id,
            'run_step_id' => $step->id,
            'tool' => 'shell',
            'status' => 'ok',
        ]);
        $memory = Memory::query()->create([
            'type' => 'preference',
            'content' => 'keep me',
            'is_active' => true,
        ]);
        MemoryRunLink::query()->create([
            'memory_id' => $memory->id,
            'run_id' => $run->id,
        ]);
        $runArtifact = Artifact::query()->create([
            'type' => 'plan',
            'name' => 'Run plan',
            'content' => 'produced by a run',
        ]);

        // --- Knowledge: must be cleared by --fresh ---
        $knowledgeArtifact = Artifact::query()->create([
            'type' => 'reference',
            'name' => 'Old reference',
            'content' => 'stale knowledge',
        ]);
        $skill = Skill::query()->create(['name' => 'old-skill', 'content' => 'stale skill']);
        $rule = Rule::query()->create(['name' => 'old-rule', 'rule_text' => 'stale rule']);
        $playbook = Playbook::query()->create(['name' => 'old-playbook', 'content' => 'stale playbook']);
        $checklist = Checklist::query()->create(['name' => 'old-checklist', 'content' => 'stale checklist']);

        $this->artisan('bosskuai:import-knowledge', ['--fresh' => true])->assertSuccessful();

        // Run history + memory preserved.
        $this->assertDatabaseHas('bossku_ai_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('bossku_ai_run_steps', ['id' => $step->id]);
        $this->assertDatabaseHas('bossku_ai_tool_calls', ['run_id' => $run->id]);
        $this->assertDatabaseHas('bossku_ai_memories', ['id' => $memory->id]);
        $this->assertDatabaseHas('bossku_ai_memory_run_links', [
            'memory_id' => $memory->id,
            'run_id' => $run->id,
        ]);
        $this->assertDatabaseHas('bossku_ai_artifacts', ['id' => $runArtifact->id]);

        // Knowledge cleared.
        $this->assertDatabaseMissing('bossku_ai_artifacts', ['id' => $knowledgeArtifact->id]);
        $this->assertDatabaseMissing('bossku_ai_skills', ['id' => $skill->id]);
        $this->assertDatabaseMissing('bossku_ai_rules', ['id' => $rule->id]);
        $this->assertDatabaseMissing('bossku_ai_playbooks', ['id' => $playbook->id]);
        $this->assertDatabaseMissing('bossku_ai_checklists', ['id' => $checklist->id]);

        // Freshly imported knowledge present.
        $this->assertDatabaseHas('bossku_ai_skills', ['name' => 'hello-import']);

        File::deleteDirectory($repo);
    }
}
