<?php

namespace Tests\Feature\Kernel;

use App\Models\BosskuAi\Run;
use App\Services\Kernel\Checkpoint\Checkpoint;
use App\Services\Kernel\Checkpoint\DatabaseCheckpointSaver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckpointEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bossku.api_auth_enabled' => false]);
    }

    private function seedRunWithCheckpoint(): array
    {
        $run = Run::query()->create(['prompt' => 'demo', 'status' => 'running']);
        $saver = new DatabaseCheckpointSaver;
        $cp = new Checkpoint(
            id: Checkpoint::newId(),
            parentId: null,
            channelValues: ['plan' => ['v' => 'demo-plan']],
            next: ['executor'],
            step: 1,
            source: 'loop',
        );
        $saver->put((string) $run->id, $cp);

        return [$run, $cp];
    }

    #[Test]
    public function checkpoints_endpoint_lists_history(): void
    {
        [$run, $cp] = $this->seedRunWithCheckpoint();

        $this->getJson("/api/runs/{$run->id}/checkpoints")
            ->assertOk()
            ->assertJsonPath('run_id', (string) $run->id)
            ->assertJsonPath('checkpoints.0.id', $cp->id)
            ->assertJsonPath('checkpoints.0.next.0', 'executor');
    }

    #[Test]
    public function fork_endpoint_creates_a_forked_run(): void
    {
        [$run, $cp] = $this->seedRunWithCheckpoint();

        $response = $this->postJson("/api/runs/{$run->id}/fork", [
            'checkpoint_id' => $cp->id,
            'state_patch' => ['plan' => 'patched-plan'],
        ])->assertCreated();

        $forkId = $response->json('forked_run_id');
        $this->assertNotNull($forkId);
        $this->assertDatabaseHas('bossku_ai_runs', ['id' => $forkId, 'run_kind' => 'fork', 'parent_run_id' => $run->id]);
        $this->assertDatabaseHas('bossku_ai_checkpoints', ['thread_id' => $forkId, 'source' => 'fork']);
    }

    #[Test]
    public function fork_endpoint_validates_checkpoint_id(): void
    {
        [$run] = $this->seedRunWithCheckpoint();

        $this->postJson("/api/runs/{$run->id}/fork", [])
            ->assertStatus(422);
    }
}
