<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\RunEventFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunEventFactoryProofTest extends TestCase
{
    #[Test]
    public function executor_artifacts_include_proof_fields(): void
    {
        $factory = new RunEventFactory;
        $arts = $factory->executorArtifacts([
            'files_read' => [['path' => 'app/Foo.php', 'reason' => 'read']],
            'files_changed' => [['path' => 'routes/web.php', 'change_type' => 'modified']],
            'needs_user_input' => true,
            'blockers' => ['Ambiguous target file'],
            'suggested_options' => ['Use routes/api.php'],
        ]);

        $this->assertTrue($arts['needs_user_input']);
        $this->assertSame(['Ambiguous target file'], $arts['blockers']);
        $this->assertContains('app/Foo.php', $arts['proof_files']);
        $this->assertContains('routes/web.php', $arts['proof_files']);
    }

    #[Test]
    public function executor_artifacts_include_commands_executed_and_git_status(): void
    {
        $factory = new RunEventFactory;
        $arts = $factory->executorArtifacts([
            'commands_run' => [['command' => 'git restore app/Foo.php']],
            '_commands_executed' => [['command' => 'git restore app/Foo.php', 'ok' => true, 'exit_code' => 0]],
            'git_status_after' => ' M app/Foo.php',
        ]);

        $this->assertSame('git restore app/Foo.php', $arts['commands_run'][0]['command']);
        $this->assertTrue($arts['commands_executed'][0]['ok']);
        $this->assertSame(' M app/Foo.php', $arts['git_status_after']);
    }

    #[Test]
    public function clarification_requested_includes_origin_and_proof(): void
    {
        $run = new Run(['id' => 'test-run-id', 'prompt' => 'test']);
        $factory = new RunEventFactory;
        $evt = $factory->clarificationRequested(
            $run,
            [['id' => 'q1', 'prompt' => 'Proceed?', 'options' => []]],
            'executor_escalation',
            'Need your choice',
            [],
            'executor',
            'executor_escalation',
            ['proof_files' => ['app/Foo.php'], 'blockers' => ['destructive delete']],
        );

        $this->assertSame('executor', $evt['from_agent']);
        $this->assertSame('executor_escalation', $evt['origin']);
        $this->assertSame('executor_escalation', $evt['artifacts']['clarification']['origin']);
        $this->assertSame(['app/Foo.php'], $evt['artifacts']['proof']['proof_files']);
    }
}
