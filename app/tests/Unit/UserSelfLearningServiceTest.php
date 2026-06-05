<?php

namespace Tests\Unit;

use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Run;
use App\Services\Learning\UserSelfLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserSelfLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_user_learning_memory_after_run(): void
    {
        $run = Run::factory()->create([
            'prompt' => 'Improve Bossku auditor for full repo audits',
            'status' => 'completed',
        ]);

        $result = app(UserSelfLearningService::class)->processAfterRun(
            $run,
            $run->prompt,
            [],
            ['skill' => 'generic', 'workflow' => 'orchestrator_executor_auditor'],
            ['summary' => 'Plan audit'],
            ['patch_summary' => 'Read orchestrator files'],
            ['status' => 'pass_with_notes'],
        );

        $this->assertNotNull($result['memory_id']);
        $this->assertDatabaseHas('bossku_ai_memories', [
            'id' => $result['memory_id'],
            'type' => 'user_learning',
        ]);
    }
}
