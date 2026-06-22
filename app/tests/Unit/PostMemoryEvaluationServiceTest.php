<?php

namespace Tests\Unit;

use App\Services\Orchestrator\PostMemoryEvaluationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostMemoryEvaluationServiceTest extends TestCase
{
    #[Test]
    public function it_scores_final_response_proof_and_memory_quality(): void
    {
        $service = new PostMemoryEvaluationService;

        $result = $service->evaluate(
            finalOutput: 'Completed. I updated the controller, ran the focused tests, and captured the decision in memory.',
            memPayload: [
                ['id' => 'mem-1', 'summary' => 'Keep route evaluation after durable memory sync.', 'type' => 'decision'],
            ],
            execResult: [
                'files_read' => [['path' => 'app/Http/Controllers/UserController.php']],
                'files_changed' => [['path' => 'app/Http/Controllers/UserController.php', 'change_type' => 'modified']],
                'commands_run' => [['command' => 'php artisan test --filter=UserControllerTest']],
                'tests_run' => [['name' => 'UserControllerTest', 'status' => 'passed']],
            ],
            lastAudit: ['status' => 'pass_with_notes', 'findings' => []],
            learningResult: ['stored' => true, 'summary' => 'Captured durable run outcome.'],
        );

        $this->assertSame('evaluator', $result['agent']);
        $this->assertSame('post_memory_eval', $result['stage']);
        $this->assertSame('memory', $result['from_agent']);
        $this->assertSame('system', $result['to_agent']);
        $this->assertSame('pass', $result['verdict']);
        $this->assertGreaterThanOrEqual(0.7, $result['score']);
        $this->assertCount(3, $result['dimensions']);
        $this->assertSame('final_response', $result['dimensions'][0]['id']);
        $this->assertStringContainsString('memory', strtolower($result['recommendation']));
    }

    #[Test]
    public function it_penalizes_generic_merge_advice_without_merge_evidence(): void
    {
        $service = new PostMemoryEvaluationService;

        $result = $service->evaluate(
            finalOutput: "## Next recommended step\nRun the relevant test suite before merge.",
            memPayload: [],
            execResult: [],
            lastAudit: ['status' => 'pass'],
            learningResult: [],
            contextAnchors: [
                'target_paths' => ['docs/PRODUCT_SPEC.md'],
                'active_repo' => 'Safwan',
            ],
        );

        $this->assertLessThan(0.8, $result['dimensions'][0]['score']);
    }

    #[Test]
    public function it_rewards_preserved_target_paths_and_prompt_suggestions(): void
    {
        $service = new PostMemoryEvaluationService;

        $result = $service->evaluate(
            finalOutput: "## Next recommended step\nInspect `docs/PRODUCT_SPEC.md`.\n\n## Prompt suggestions\nContinue: Read `docs/PRODUCT_SPEC.md` in the active repo.",
            memPayload: [],
            execResult: [],
            lastAudit: ['status' => 'pass'],
            learningResult: [],
            contextAnchors: [
                'target_paths' => ['docs/PRODUCT_SPEC.md'],
            ],
        );

        $this->assertGreaterThanOrEqual(0.75, $result['dimensions'][0]['score']);
    }
}
