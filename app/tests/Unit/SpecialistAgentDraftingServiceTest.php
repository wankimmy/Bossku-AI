<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillCandidate;
use App\Services\Specialists\SpecialistAgentDraftingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialistAgentDraftingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_drafts_a_project_scoped_specialist_and_skill_candidate_from_run_artifacts(): void
    {
        $project = Project::query()->create([
            'name' => 'Bossku-AI',
            'host_path' => '/workspace/Bossku-AI',
            'container_path' => '/workspace/Bossku-AI',
            'is_active' => true,
        ]);
        $skill = Skill::query()->create([
            'name' => 'laravel-orchestrator',
            'description' => 'Laravel orchestrator work.',
            'content' => '# Laravel Orchestrator',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        $run = Run::factory()->create([
            'prompt' => 'Fix the orchestrator approval flow for file writes.',
            'selected_skill_name' => 'laravel-orchestrator',
            'metadata' => ['active_project_id' => $project->id],
            'status' => 'completed',
        ]);

        $agent = app(SpecialistAgentDraftingService::class)->draftFromRun($run, [
            'skill_name' => 'laravel-orchestrator',
            'planner_output' => [
                'summary' => 'Plan approval flow fix.',
                'target_file_list' => [
                    ['path' => 'app/Services/Orchestrator/OrchestratorApprovalTrait.php'],
                ],
            ],
            'executor_result' => [
                'patch_summary' => 'Updated approval handoff.',
            ],
            'memory_signals' => [
                ['summary' => 'Past approval runs failed when file-write context was missing.'],
            ],
        ], force: true);

        $this->assertSame($project->id, $agent->project_id);
        $this->assertSame('draft', $agent->approval_status);
        $this->assertSame($skill->id, $agent->linked_skill_id);
        $this->assertContains('orchestrator', $agent->trigger_keywords);
        $this->assertStringContainsString('approval', $agent->persona_content);
        $this->assertDatabaseHas('bossku_ai_skill_candidates', [
            'approval_status' => 'draft',
            'category' => 'specialist-agent',
        ]);

        $candidate = SkillCandidate::query()->where('category', 'specialist-agent')->firstOrFail();
        $this->assertContains($run->id, $candidate->source_run_ids);
        $this->assertSame($candidate->id, $agent->metadata['skill_candidate_id'] ?? null);
    }
}
