<?php

namespace Tests\Feature\Kernel;

use App\Models\BosskuAi\Assistant;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bossku.api_auth_enabled' => false]);
    }

    #[Test]
    public function graph_topology_endpoint_returns_the_default_pipeline(): void
    {
        $this->getJson('/api/graphs/default_pipeline')
            ->assertOk()
            ->assertJsonPath('name', 'default_pipeline')
            ->assertJsonPath('entry', 'router')
            ->assertJsonFragment(['from' => 'planner', 'to' => 'executor']);

        $this->getJson('/api/graphs/nope')->assertNotFound();
    }

    #[Test]
    public function assistant_crud_round_trips(): void
    {
        $created = $this->postJson('/api/assistants', [
            'name' => 'Nightly Auditor',
            'graph' => 'default_pipeline',
            'config' => ['workflow' => 'orchestrator_executor_auditor'],
        ])->assertCreated()->json();

        $this->assertNotEmpty($created['slug']);

        $this->getJson("/api/assistants/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('name', 'Nightly Auditor');

        $this->patchJson("/api/assistants/{$created['id']}", ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->deleteJson("/api/assistants/{$created['id']}")->assertOk();
        $this->assertDatabaseMissing('bossku_ai_assistants', ['id' => $created['id']]);
    }

    #[Test]
    public function assistant_rejects_unknown_graph(): void
    {
        $this->postJson('/api/assistants', ['name' => 'X', 'graph' => 'not_a_graph'])
            ->assertStatus(422);
    }

    #[Test]
    public function thread_groups_its_runs(): void
    {
        $thread = Thread::query()->create(['title' => 'Conversation 1']);
        Run::query()->create(['prompt' => 'first', 'status' => 'completed', 'thread_id' => $thread->id]);
        Run::query()->create(['prompt' => 'second', 'status' => 'completed', 'thread_id' => $thread->id]);
        Run::query()->create(['prompt' => 'unrelated', 'status' => 'completed']);

        $this->getJson("/api/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('thread.title', 'Conversation 1')
            ->assertJsonCount(2, 'runs');
    }

    #[Test]
    public function cron_job_creation_validates_expression_and_sets_next_run(): void
    {
        $assistant = Assistant::query()->create(['name' => 'A', 'slug' => 'a-'.uniqid(), 'graph' => 'default_pipeline']);

        $this->postJson('/api/cron-jobs', [
            'assistant_id' => $assistant->id,
            'name' => 'bad',
            'cron_expression' => 'not a cron',
        ])->assertStatus(422);

        $created = $this->postJson('/api/cron-jobs', [
            'assistant_id' => $assistant->id,
            'name' => 'nightly',
            'cron_expression' => '0 0 * * *',
            'prompt' => 'run the audit',
        ])->assertCreated()->json();

        $this->assertNotNull($created['next_run_at']);
    }

    #[Test]
    public function webhook_creation_validates_events(): void
    {
        $this->postJson('/api/webhooks', [
            'url' => 'https://example.test/hook',
            'events' => ['totally.invalid'],
        ])->assertStatus(422);

        $this->postJson('/api/webhooks', [
            'url' => 'https://example.test/hook',
            'events' => ['run.completed', 'checkpoint.created'],
            'secret' => 'shh',
        ])->assertCreated();

        // Secret is hidden in responses.
        $this->getJson('/api/webhooks')
            ->assertOk()
            ->assertJsonMissing(['secret' => 'shh']);
    }
}
