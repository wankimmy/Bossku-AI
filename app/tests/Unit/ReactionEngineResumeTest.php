<?php

namespace Tests\Unit;

use App\Jobs\ResumeRunFromReactionJob;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Scm\ReactionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReactionEngineResumeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resume_run_action_dispatches_resume_job(): void
    {
        Queue::fake();
        config([
            'bossku.reactions.ci_failed' => [
                'auto' => true,
                'action' => 'resume_run',
                'retries' => 5,
            ],
        ]);

        $repo = sys_get_temp_dir().'/bkreact_'.uniqid();
        mkdir($repo, 0777, true);

        Project::query()->create([
            'name' => 'React',
            'host_path' => $repo,
            'container_path' => $repo,
            'is_active' => true,
        ]);

        $run = Run::query()->create([
            'prompt' => 'task',
            'status' => 'completed',
            'metadata' => [
                'scm' => [
                    'owner' => 'acme',
                    'repo' => 'app',
                    'pull_number' => 1,
                ],
            ],
        ]);

        $engine = app(ReactionEngine::class);
        $method = new \ReflectionMethod($engine, 'executeReaction');
        $method->setAccessible(true);
        $method->invoke($engine, $run, 'ci_failed', ['state' => 'failed']);

        Queue::assertPushed(ResumeRunFromReactionJob::class, function (ResumeRunFromReactionJob $job) use ($run) {
            return $job->runId === (string) $run->getKey() && $job->reactionKey === 'ci_failed';
        });
    }
}
