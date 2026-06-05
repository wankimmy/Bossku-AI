<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class UserLocalCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detects_docker_blocked_commands(): void
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'commandsRequiringUserRun');
        $method->setAccessible(true);

        $needs = $method->invoke($service, [
            [
                'command' => 'docker compose ps',
                'ok' => false,
                'skipped' => true,
                'reason' => 'Command blocked: docker compose requires /var/run/docker.sock on the Bossku backend (local dev).',
            ],
            [
                'command' => 'git status --porcelain',
                'ok' => true,
                'stdout' => '',
            ],
        ]);

        $this->assertCount(1, $needs);
        $this->assertSame('docker compose ps', $needs[0]['command']);
    }

    #[Test]
    public function builds_clarification_questions_for_blocked_commands(): void
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'buildUserLocalCommandsClarification');
        $method->setAccessible(true);

        $clarification = $method->invoke($service, ['npm run build:pixel-office', 'docker compose logs backend']);

        $this->assertCount(2, $clarification['questions']);
        $this->assertStringContainsString('npm run build:pixel-office', $clarification['questions'][0]['prompt']);
        $this->assertTrue($clarification['questions'][0]['allow_free_text']);
    }

    #[Test]
    public function should_pause_when_blocked_commands_exist(): void
    {
        $run = Run::factory()->create(['prompt' => 'test', 'status' => 'running']);
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'shouldPauseForUserLocalCommands');
        $method->setAccessible(true);

        $execResult = [
            '_commands_executed' => [[
                'command' => 'docker compose exec backend php artisan test',
                'ok' => false,
                'reason' => 'Command blocked: docker compose requires /var/run/docker.sock',
            ]],
        ];

        $this->assertTrue($method->invoke($service, $run, $execResult));
    }

    #[Test]
    public function local_command_pause_carries_awaiting_input_checklist_status(): void
    {
        $run = Run::factory()->create(['prompt' => 'test', 'status' => 'running']);
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'maybePauseForUserLocalCommands');
        $method->setAccessible(true);

        $events = [];
        $execResult = [
            'status' => 'success',
            'checklist_status' => [[
                'id' => 'plan-1',
                'status' => 'completed',
                'notes' => 'Executor should not stay completed while npm install is blocked.',
            ]],
            '_commands_executed' => [[
                'command' => 'npm install',
                'ok' => false,
                'exit_code' => 127,
                'stderr' => 'sh: 1: npm: not found',
            ]],
        ];
        $pipeline = [
            'plan' => [
                'checklist' => [[
                    'id' => 'plan-1',
                    'title' => 'Scaffold Nuxt project with dependencies',
                    'owner' => 'executor',
                    'status' => 'pending',
                ]],
            ],
        ];

        $result = $method->invoke($service, $run, $execResult, $pipeline, function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $this->assertTrue($result['awaiting_clarification']);
        $this->assertSame('user_local_commands', $events[0]['stage']);
        $proof = $events[0]['artifacts']['proof'];
        $this->assertTrue($proof['needs_user_input']);
        $this->assertSame('awaiting_input', $proof['checklist_status'][0]['status']);
        $this->assertSame('plan-1', $proof['checklist_status'][0]['id']);
        $this->assertStringContainsString('npm install', $proof['blockers'][0]);

        $checkpointProof = $run->fresh()->metadata['checkpoint']['clarification']['proof'];
        $this->assertSame('awaiting_input', $checkpointProof['checklist_status'][0]['status']);
    }

    #[Test]
    public function detects_command_not_found_exit_127(): void
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'commandsRequiringUserRun');
        $method->setAccessible(true);

        $needs = $method->invoke($service, [
            [
                'command' => 'npm install',
                'ok' => false,
                'exit_code' => 127,
                'stderr' => 'sh: 1: npm: not found',
            ],
        ]);

        $this->assertCount(1, $needs);
        $this->assertSame('npm install', $needs[0]['command']);
        $this->assertStringContainsString('exit 127', $needs[0]['reason']);
    }

    #[Test]
    public function ignores_genuine_command_failures(): void
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'commandsRequiringUserRun');
        $method->setAccessible(true);

        // A failing test suite and an npm registry 404 are real failures the
        // agent should handle — they must NOT be handed back to the user.
        $needs = $method->invoke($service, [
            [
                'command' => 'php artisan test',
                'ok' => false,
                'exit_code' => 1,
                'stderr' => 'Tests: 1 failed, 5 passed',
            ],
            [
                'command' => 'npm install left-pad',
                'ok' => false,
                'exit_code' => 1,
                'stderr' => 'npm ERR! 404 Not Found - GET https://registry.npmjs.org/left-pad',
            ],
        ]);

        $this->assertSame([], $needs);
    }

    #[Test]
    public function merges_user_output_for_command_not_found_row(): void
    {
        $service = app(OrchestratorService::class);

        $build = new ReflectionMethod(OrchestratorService::class, 'buildUserLocalCommandsClarification');
        $build->setAccessible(true);
        $clarification = $build->invoke($service, ['npm install']);
        $questions = $clarification['questions'];

        $merge = new ReflectionMethod(OrchestratorService::class, 'mergeUserCommandOutputsIntoExecResult');
        $merge->setAccessible(true);

        $execResult = [
            'status' => 'partial',
            '_commands_executed' => [[
                'command' => 'npm install',
                'ok' => false,
                'exit_code' => 127,
                'stderr' => 'sh: 1: npm: not found',
            ]],
        ];
        $answers = [[
            'question_id' => 'user_cmd_1',
            'free_text' => 'added 10 packages in 2s',
        ]];

        $merged = $merge->invoke($service, $execResult, $answers, $questions);
        $row = $merged['_commands_executed'][0];

        $this->assertTrue($row['ok']);
        $this->assertTrue($row['user_provided']);
        $this->assertSame('added 10 packages in 2s', $row['stdout']);
        $this->assertSame('success', $merged['status']);
    }
}
