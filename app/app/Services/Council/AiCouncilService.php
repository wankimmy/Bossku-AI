<?php

namespace App\Services\Council;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Company\CompanyStaffService;
use App\Services\Specialists\SpecialistIntent;
use App\Services\Specialists\SpecialistIntentClassifier;
use App\Support\PromptContextHelper;
use Illuminate\Support\Collection;

class AiCouncilService
{
    public const MAX_AGENTS = 5;

    public const MAX_ROUNDS = 2;

    public function __construct(
        protected RuntimeSettings $settings,
        protected CompanyStaffService $staff,
        protected SpecialistIntentClassifier $intentClassifier,
        protected CouncilQuestionService $questions,
        protected ModelRoutingConfig $modelConfig,
        protected ModelFallbackService $fallback,
    ) {}

    /**
     * @param  array<string, mixed>  $modelRoute
     * @return array{
     *   status: string,
     *   reason?: string|null,
     *   draft?: string,
     *   final_output?: string,
     *   voices?: list<array<string, mixed>>,
     *   questions?: list<array<string, mixed>>,
     *   rounds?: int,
     *   intent?: string
     * }
     */
    public function deliberate(
        Run $run,
        string $userPrompt,
        string $draft,
        array $modelRoute,
        ?Project $project,
        array $conversation = [],
        bool $precheckDone = false,
    ): array {
        if ($this->shouldSkip($userPrompt, $modelRoute)) {
            return [
                'status' => 'skipped',
                'reason' => 'trivial_prompt',
                'draft' => $draft,
                'final_output' => $draft,
                'voices' => [],
                'rounds' => 0,
            ];
        }

        $intent = $this->intentClassifier->classify($userPrompt, $modelRoute);
        $modelRoute['specialist_intent'] = $intent->value;

        $questionCheck = $precheckDone
            ? ['needs_questions' => false, 'already_answered' => true, 'questions' => []]
            : $this->questions->analyze($userPrompt, $modelRoute, $conversation);
        if ($questionCheck['needs_questions'] && ! $questionCheck['already_answered']) {
            return [
                'status' => 'needs_clarification',
                'reason' => 'missing_information',
                'draft' => $draft,
                'questions' => $questionCheck['questions'],
                'voices' => [],
                'rounds' => 0,
                'intent' => $intent->value,
            ];
        }

        $voices = $this->buildVoices($userPrompt, $draft, $modelRoute, $project, $intent);
        $critique = $this->critiqueVoices($voices, $draft);
        $revised = $this->synthesize($run, $userPrompt, $draft, $critique, $modelRoute, $intent);

        return [
            'status' => 'completed',
            'reason' => null,
            'draft' => $draft,
            'final_output' => $revised,
            'voices' => $critique,
            'rounds' => 1,
            'intent' => $intent->value,
            'consensus' => 'Council reviewed the draft and synthesized one final answer.',
        ];
    }

    /** @param  array<string, mixed>  $modelRoute */
    protected function shouldSkip(string $userPrompt, array $modelRoute): bool
    {
        if (! $this->settings->aiCouncilEnabled() || ! $this->settings->companyStaffEnabled()) {
            return true;
        }

        $current = trim(PromptContextHelper::currentRequest($userPrompt));
        if (preg_match('/^(test|ping|hello|hi|hey|thanks|thank you|thx|ty|ok|okay|cool)\s*[!?.]*$/i', $current)) {
            return true;
        }

        if (PromptContextHelper::isMetaAboutAssistant($userPrompt) && ($modelRoute['workflow'] ?? '') === 'direct_answer') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @return list<array<string, mixed>>
     */
    protected function buildVoices(
        string $prompt,
        string $draft,
        array $modelRoute,
        ?Project $project,
        SpecialistIntent $intent,
    ): array {
        if ($project === null) {
            return $this->syntheticVoices($intent);
        }

        $selected = $this->staff->selectForCouncil($prompt.' '.$draft, [], $modelRoute, $project);
        if ($selected->isEmpty()) {
            $selected = $this->staffByIntent($project, $intent);
        }

        return $selected
            ->take(self::MAX_AGENTS)
            ->map(fn (SpecialistAgent $agent) => [
                'role_slug' => $agent->role_slug,
                'display_name' => $agent->display_name,
                'runtime_mode' => $agent->runtime_mode,
                'position' => $this->staff->positionFor($agent),
                'persona' => $agent->persona_content,
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, SpecialistAgent> */
    protected function staffByIntent(Project $project, SpecialistIntent $intent): Collection
    {
        $this->staff->seedDefaults($project);
        $slugs = $intent->staffRoleSlugs();

        return SpecialistAgent::query()
            ->where('project_id', $project->id)
            ->where('is_company_staff', true)
            ->where('staff_active', true)
            ->whereIn('role_slug', $slugs)
            ->orderBy('staff_sort_order')
            ->limit(self::MAX_AGENTS)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    protected function syntheticVoices(SpecialistIntent $intent): array
    {
        $voices = [];
        foreach ($this->staff->defaultRoster() as $row) {
            if (! in_array($row['role_slug'], $intent->staffRoleSlugs(), true)) {
                continue;
            }
            $voices[] = [
                'role_slug' => $row['role_slug'],
                'display_name' => $row['display_name'],
                'runtime_mode' => $row['runtime_mode'],
                'position' => $row['description'],
                'persona' => $row['persona_content'],
            ];
        }

        return array_slice($voices, 0, self::MAX_AGENTS);
    }

    /**
     * @param  list<array<string, mixed>>  $voices
     * @return list<array<string, mixed>>
     */
    protected function critiqueVoices(array $voices, string $draft): array
    {
        $out = [];
        foreach ($voices as $voice) {
            $role = (string) ($voice['role_slug'] ?? 'critic');
            $out[] = array_merge($voice, [
                'critique' => $this->critiqueForRole($role, $draft),
                'confidence' => 0.72,
            ]);
        }

        if ($out === []) {
            $out[] = [
                'role_slug' => 'critic',
                'display_name' => 'Critic',
                'position' => 'Challenge weak assumptions before the answer ships.',
                'critique' => 'Check whether the draft is specific, actionable, and free of unsupported claims.',
                'confidence' => 0.65,
            ];
        }

        return $out;
    }

    protected function critiqueForRole(string $role, string $draft): string
    {
        $short = mb_strlen($draft) < 120;

        return match ($role) {
            'seo-writer' => $short
                ? 'Add search intent, target keyword theme, and a concrete meta title/description suggestion.'
                : 'Verify headings map to search intent and avoid keyword stuffing.',
            'marketing-manager' => $short
                ? 'Clarify audience, positioning, and channel before recommending tactics.'
                : 'Tighten positioning and remove claims the product cannot support.',
            'sales-manager' => $short
                ? 'Name the buyer pain, objection, and next action explicitly.'
                : 'Make the offer concrete and tie claims to buyer value.',
            'ui-ux-designer' => $short
                ? 'Call out layout, hierarchy, and mobile usability risks.'
                : 'Check scanability, affordances, and error-state clarity.',
            'qa' => 'Define acceptance checks and likely regression risks.',
            'security' => 'Flag permission, privacy, secret-handling, or abuse risks if implementation is implied.',
            'tech-lead' => 'Keep scope bounded and aligned with maintainable architecture.',
            'project-manager' => 'Break ambiguous work into approved, trackable next steps.',
            default => 'Make the answer more specific and actionable.',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $critique
     * @param  array<string, mixed>  $modelRoute
     */
    protected function synthesize(
        Run $run,
        string $userPrompt,
        string $draft,
        array $critique,
        array $modelRoute,
        SpecialistIntent $intent,
    ): string {
        $workflow = (string) ($modelRoute['workflow'] ?? 'direct_answer');
        $role = $workflow === 'writer_only' ? 'writer' : 'direct_answer';
        $cfg = $role === 'writer' ? $this->modelConfig->writer() : $this->modelConfig->directAnswer();
        $primary = (string) ($cfg['primary'] ?? 'gpt-4o-mini');
        $fallbacks = $cfg['fallback'] ?? [];
        $models = array_merge([$primary], is_array($fallbacks) ? $fallbacks : []);

        $messages = [
            ['role' => 'system', 'content' => 'You are the BosskuAI council synthesizer. Merge the draft answer with staff critiques into one clear final response. Do not expose raw debate unless asked. No JSON.'],
            ['role' => 'user', 'content' => json_encode([
                'question' => $userPrompt,
                'intent' => $intent->value,
                'draft' => $draft,
                'critiques' => $critique,
            ], JSON_THROW_ON_ERROR)],
        ];

        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                $messages,
                (float) ($cfg['temperature'] ?? 0.25),
                (int) ($cfg['retry_count'] ?? 1),
                $role,
                null,
                null,
                $run->id,
            );

            return trim((string) $out['text']) !== '' ? trim((string) $out['text']) : $draft;
        } catch (\Throwable) {
            return $this->offlineSynthesis($draft, $critique);
        }
    }

    /** @param  list<array<string, mixed>>  $critique */
    protected function offlineSynthesis(string $draft, array $critique): string
    {
        $notes = [];
        foreach ($critique as $voice) {
            $notes[] = '- '.($voice['display_name'] ?? $voice['role_slug'] ?? 'Staff').': '.($voice['critique'] ?? '');
        }
        if ($notes === []) {
            return $draft;
        }

        return trim($draft)."\n\n## Council notes\n".implode("\n", $notes);
    }
}
