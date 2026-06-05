<?php

namespace App\Services\Learning;

use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Run;
use App\Support\StringCoercion;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Project\BosskuToolkitDetector;
use App\Services\Project\ProjectPathResolver;
use Illuminate\Support\Str;

/**
 * Captures per-prompt user signals and persists them to memory + learning events.
 */
class UserSelfLearningService
{
    public function __construct(
        protected MemoryService $memory,
        protected LearningEngine $learningEngine,
        protected RuntimeSettings $settings,
        protected BosskuToolkitDetector $toolkitDetector,
        protected ProjectPathResolver $paths,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     */
    public function processAfterRun(
        Run $run,
        string $prompt,
        array $conversation,
        array $modelRoute,
        array $plan = [],
        array $execResult = [],
        array $lastAudit = [],
    ): array {
        if (! $this->settings->memoryStorageEnabled()) {
            return ['memory_id' => null, 'learning_events' => 0];
        }

        $activeProject = $this->paths->activeProject();
        $isToolkit = $this->toolkitDetector->isBosskuToolkitRepository();

        $learnings = $this->extractUserLearnings($prompt, $conversation, $modelRoute, $plan, $execResult, $lastAudit, $isToolkit);
        $memory = $this->persistUserLearningMemory($run, $prompt, $learnings, $activeProject?->name, $isToolkit);

        $extractions = $this->learningEngine->extractFromRun($run->fresh(), $execResult, $lastAudit);
        $this->learningEngine->saveEvents($run, $extractions);

        return [
            'memory_id' => $memory?->id,
            'learning_events' => count($extractions),
            'learnings' => $learnings,
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     * @return list<string>
     */
    protected function extractUserLearnings(
        string $prompt,
        array $conversation,
        array $modelRoute,
        array $plan,
        array $execResult,
        array $lastAudit,
        bool $isToolkit,
    ): array {
        $items = [];

        $trimmed = trim($prompt);
        if ($trimmed !== '') {
            $items[] = 'Latest user intent: '.Str::limit($trimmed, 400);
        }

        if ($conversation !== []) {
            $items[] = 'Conversation turns: '.count($conversation);
        }

        $skill = (string) ($modelRoute['skill'] ?? $plan['selected_skill'] ?? '');
        if ($skill !== '') {
            $items[] = 'Skill/route context: '.$skill.' / '.(string) ($modelRoute['workflow'] ?? '');
        }

        if ($isToolkit) {
            $items[] = 'Context: user is improving the Bossku-AI orchestrator codebase.';
        }

        $patch = StringCoercion::toString($execResult['patch_summary'] ?? null, '');
        if ($patch !== '') {
            $items[] = 'Executor outcome: '.Str::limit($patch, 300);
        }

        $auditStatus = (string) ($lastAudit['status'] ?? '');
        if ($auditStatus !== '') {
            $items[] = 'Auditor status: '.$auditStatus;
        }

        return array_values(array_unique($items));
    }

    /**
     * @param  list<string>  $learnings
     */
    protected function persistUserLearningMemory(
        Run $run,
        string $prompt,
        array $learnings,
        ?string $projectName,
        bool $isToolkit,
    ): ?\App\Models\BosskuAi\Memory {
        if ($learnings === []) {
            return null;
        }

        $payload = [
            'run_id' => $run->id,
            'project' => $projectName,
            'bossku_toolkit' => $isToolkit,
            'learnings' => $learnings,
            'prompt_excerpt' => Str::limit($prompt, 200),
            'recorded_at' => now()->toIso8601String(),
        ];

        try {
            $memory = $this->memory->store(
                json_encode($payload, JSON_THROW_ON_ERROR),
                'user_learning',
                ['run_id' => $run->id, 'self_learning' => true],
                Str::limit(implode(' | ', $learnings), 200),
                array_values(array_filter(['user', 'learning', $isToolkit ? 'bossku-toolkit' : 'project'])),
                'user_self_learning',
            );

            MemoryRunLink::query()->firstOrCreate(
                ['memory_id' => $memory->id, 'run_id' => $run->id],
                ['similarity_score' => 1.0],
            );

            return $memory;
        } catch (\Throwable) {
            return null;
        }
    }
}
